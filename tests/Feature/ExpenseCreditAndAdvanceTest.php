<?php

use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\ExpenseVendor;
use App\Models\ExpenseVendorPayment;
use App\Models\ExpenseVoucher;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ExpenseVendorPaymentService;
use App\Services\ExpenseVoucherService;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['expense-voucher-list', 'expense-voucher-create', 'expense-voucher-approve', 'expense-voucher-view-company', 'expense-vendor-payment-list', 'expense-vendor-payment-create', 'expense-vendor-payment-approve', 'expense-vendor-payment-view-company', 'report-audit-cash-collection-distributor', 'report-audit-cash-collection-distributor-all-companies'] as $name) {
        Permission::findOrCreate($name);
    }

    $this->ledger = seedExpenseLedger();
    $this->supplier = Supplier::factory()->create();
    $this->moonTraders = Company::first() ?? Company::factory()->create(['company_name' => 'Moon Traders', 'abbr' => 'MT']);
    $this->vendor = ExpenseVendor::factory()->create(['name' => 'Raza Auto Works']);

    $this->maker = User::factory()->create(['supplier_id' => $this->supplier->id])->givePermissionTo(['expense-voucher-list', 'expense-voucher-create', 'expense-vendor-payment-list', 'expense-vendor-payment-create']);
    $this->checker = User::factory()->create(['supplier_id' => $this->supplier->id])->givePermissionTo([
        'expense-voucher-list', 'expense-voucher-approve', 'expense-voucher-view-company', 'expense-vendor-payment-list', 'expense-vendor-payment-approve', 'expense-vendor-payment-view-company',
        'report-audit-cash-collection-distributor', 'report-audit-cash-collection-distributor-all-companies',
    ]);

    $this->makeVoucher = function (array $overrides = []) {
        $this->actingAs($this->maker);

        $voucher = ExpenseVoucher::factory()->create($overrides + [
            'supplier_id' => $this->supplier->id, 'expense_vendor_id' => $this->vendor->id, 'payee_type' => 'vendor', 'payment_type' => 'credit',
            'party_name' => $this->vendor->name, 'expense_account_id' => $this->ledger['5221']->id, 'amount' => 10000, 'status' => 'pending',
        ]);

        return $voucher;
    };

    $this->balanceOf = fn (string $code) => (float) DB::table('journal_entry_details')->where('chart_of_account_id', $this->ledger[$code]->id)->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0) as balance')->value('balance');
});

it('books a credit voucher to Expense Payables and leaves cash alone', function () {
    $voucher = ($this->makeVoucher)();

    $this->actingAs($this->checker)->post(route('expense-vouchers.approve', $voucher))->assertRedirect();

    $voucher->refresh();
    expect($voucher->status)->toBe('approved')
        ->and($voucher->payment_status)->toBe('unpaid')
        ->and((float) $voucher->paid_amount)->toBe(0.0)
        ->and(($this->balanceOf)('5221'))->toBe(10000.0)
        ->and(($this->balanceOf)('2113'))->toBe(-10000.0)
        ->and(($this->balanceOf)('1121'))->toBe(0.0)
        ->and($this->vendor->outstanding())->toBe(10000.0);
});

it('books a cash voucher against cash and marks it paid', function () {
    $voucher = ($this->makeVoucher)(['payment_type' => 'cash']);

    $this->actingAs($this->checker)->post(route('expense-vouchers.approve', $voucher))->assertRedirect();

    expect($voucher->fresh()->payment_status)->toBe('paid')
        ->and(($this->balanceOf)('1121'))->toBe(-10000.0)
        ->and($this->vendor->outstanding())->toBe(0.0);
});

it('pays credit vouchers in parts through a checked vendor payment', function () {
    $first = ($this->makeVoucher)(['amount' => 10000]);
    $second = ($this->makeVoucher)(['amount' => 4000]);
    foreach ([$first, $second] as $voucher) {
        $this->actingAs($this->checker)->post(route('expense-vouchers.approve', $voucher));
    }

    $this->actingAs($this->maker)->post(route('expense-vendor-payments.store'), [
        'payment_date' => now()->toDateString(), 'expense_vendor_id' => $this->vendor->id, 'method' => 'cash',
        'amounts' => [$first->id => 10000, $second->id => 1000],
    ])->assertRedirect();

    $payment = ExpenseVendorPayment::first();
    expect($payment->status)->toBe('pending')->and((float) $payment->amount)->toBe(11000.0)
        ->and(($this->balanceOf)('2113'))->toBe(-14000.0);

    $this->actingAs($this->maker->givePermissionTo(['expense-vendor-payment-approve', 'expense-vendor-payment-view-company']))->post(route('expense-vendor-payments.approve', $payment))->assertSessionHas('error');
    $this->actingAs($this->checker)->post(route('expense-vendor-payments.approve', $payment))->assertSessionHas('success');

    expect($first->fresh()->payment_status)->toBe('paid')
        ->and($second->fresh()->payment_status)->toBe('partial')
        ->and((float) $second->fresh()->paid_amount)->toBe(1000.0)
        ->and(($this->balanceOf)('2113'))->toBe(-3000.0)
        ->and(($this->balanceOf)('1121'))->toBe(-11000.0)
        ->and($this->vendor->outstanding())->toBe(3000.0);
});

it('refuses to pay more than a voucher owes, a voucher of another shop, or two companies at once', function () {
    $voucher = ($this->makeVoucher)(['amount' => 5000]);
    $this->actingAs($this->checker)->post(route('expense-vouchers.approve', $voucher));
    $service = app(ExpenseVendorPaymentService::class);
    $data = ['payment_date' => now()->toDateString(), 'expense_vendor_id' => $this->vendor->id, 'method' => 'cash'];

    $this->actingAs($this->maker);
    expect(fn () => $service->create($data, [$voucher->id => 6000], $this->maker))->toThrow(RuntimeException::class, 'only owes');
    expect(fn () => $service->create(['expense_vendor_id' => ExpenseVendor::factory()->create()->id] + $data, [$voucher->id => 100], $this->maker))->toThrow(RuntimeException::class, 'cannot be paid through this shop');

    $other = ($this->makeVoucher)(['supplier_id' => Supplier::factory()->create()->id, 'amount' => 100]);
    $other->update(['status' => 'approved']);
    expect(fn () => $service->create($data, [$voucher->id => 100, $other->id => 100], $this->maker))->toThrow(RuntimeException::class, 'one company');
    expect(ExpenseVendorPayment::count())->toBe(0);
});

it('does not let two pending payments clear the same voucher twice', function () {
    $voucher = ($this->makeVoucher)(['amount' => 5000]);
    $this->actingAs($this->checker)->post(route('expense-vouchers.approve', $voucher));
    $service = app(ExpenseVendorPaymentService::class);
    $data = ['payment_date' => now()->toDateString(), 'expense_vendor_id' => $this->vendor->id, 'method' => 'cash'];

    $this->actingAs($this->maker);
    $one = $service->create($data, [$voucher->id => 5000], $this->maker);
    $two = $service->create($data, [$voucher->id => 5000], $this->maker);

    $this->actingAs($this->checker)->post(route('expense-vendor-payments.approve', $one))->assertSessionHas('success');
    $this->actingAs($this->checker)->post(route('expense-vendor-payments.approve', $two))->assertSessionHas('error');

    expect($two->fresh()->status)->toBe('pending')->and(($this->balanceOf)('1121'))->toBe(-5000.0);
});

it('pays a shop from a bank account and leaves cash alone', function () {
    $bank = BankAccount::factory()->create(['chart_of_account_id' => $this->ledger['1171']->id]);
    $voucher = ($this->makeVoucher)(['amount' => 2000]);
    $this->actingAs($this->checker)->post(route('expense-vouchers.approve', $voucher));

    $this->actingAs($this->maker);
    $payment = app(ExpenseVendorPaymentService::class)->create(['payment_date' => now()->toDateString(), 'expense_vendor_id' => $this->vendor->id, 'method' => 'bank', 'bank_account_id' => $bank->id], [$voucher->id => 2000], $this->maker);
    $this->actingAs($this->checker)->post(route('expense-vendor-payments.approve', $payment))->assertSessionHas('success');

    expect(($this->balanceOf)('1171'))->toBe(-2000.0)->and(($this->balanceOf)('1121'))->toBe(0.0);
});

it('opens an advance for the employee when an advance voucher is approved', function () {
    $employee = Employee::factory()->create(['supplier_id' => $this->supplier->id, 'is_active' => true]);
    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), [
        'payee_type' => 'employee', 'employee_id' => $employee->id, 'voucher_date' => now()->toDateString(), 'amount' => 6000, 'advance_installment' => 2000, 'narration' => 'Medical',
    ])->assertRedirect();

    $voucher = ExpenseVoucher::first();
    expect($voucher->payment_type)->toBe('advance')->and($voucher->supplier_id)->toBe($this->supplier->id)->and($voucher->party_name)->toBe($employee->name);

    $this->actingAs($this->checker)->post(route('expense-vouchers.approve', $voucher))->assertRedirect();

    $advance = EmployeeAdvance::first();
    expect($advance->employee_id)->toBe($employee->id)
        ->and((float) $advance->amount)->toBe(6000.0)
        ->and($advance->balance())->toBe(6000.0)
        ->and($advance->dueThisMonth())->toBe(2000.0)
        ->and(($this->balanceOf)('1131'))->toBe(6000.0)
        ->and(($this->balanceOf)('1121'))->toBe(-6000.0)
        ->and(($this->balanceOf)('5221'))->toBe(0.0);
});

it('does not let a company user give an advance to another company\'s employee', function () {
    $stranger = Employee::factory()->create(['supplier_id' => Supplier::factory()->create()->id, 'is_active' => true]);

    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), ['payee_type' => 'employee', 'employee_id' => $stranger->id, 'voucher_date' => now()->toDateString(), 'amount' => 100])->assertForbidden();
});

it('searches only the maker\'s own company employees for an advance', function () {
    Employee::factory()->create(['supplier_id' => $this->supplier->id, 'name' => 'Own Staff Ali', 'is_active' => true]);
    Employee::factory()->create(['supplier_id' => Supplier::factory()->create()->id, 'name' => 'Other Staff Ali', 'is_active' => true]);

    $results = $this->actingAs($this->maker)->getJson(route('expense-vouchers.employees.search', ['q' => 'Ali']))->json('results');

    expect($results)->toHaveCount(1)->and($results[0]['text'])->toContain('Own Staff Ali');
});

it('keeps credit vouchers out of the cash report until they are paid in cash', function () {
    $today = now()->toDateString();
    $cash = ($this->makeVoucher)(['payment_type' => 'cash', 'amount' => 700, 'party_name' => 'Tea Stall', 'expense_vendor_id' => null, 'payee_type' => 'other']);
    $credit = ($this->makeVoucher)(['amount' => 10000]);
    foreach ([$cash, $credit] as $voucher) {
        $this->actingAs($this->checker)->post(route('expense-vouchers.approve', $voucher));
    }

    $rows = fn () => $this->actingAs($this->checker)->get(route('reports.cash-collection-distributor.index', ['supplier_id' => $this->supplier->id, 'date' => $today]))->viewData('tables');
    $summary = fn () => collect($rows()['summary']['rows'])->mapWithKeys(fn ($row) => [$row[0] => $row[1]]);

    expect(collect($rows()['expenses']['rows'])->pluck(1)->all())->toBe(['Tea Stall'])
        ->and($summary()['Less: expenses paid (cash and advance vouchers)'])->toBe('700.00')
        ->and($summary()['Less: vendor payments (cash)'])->toBe('0.00');

    $this->actingAs($this->maker);
    $payment = app(ExpenseVendorPaymentService::class)->create(['payment_date' => $today, 'expense_vendor_id' => $this->vendor->id, 'method' => 'cash'], [$credit->id => 4000], $this->maker);
    $this->actingAs($this->checker)->post(route('expense-vendor-payments.approve', $payment));

    expect($summary()['Less: vendor payments (cash)'])->toBe('4,000.00')
        ->and(collect($rows()['expenses']['rows'])->pluck(1)->all())->toContain('Vendor payment - Raza Auto Works');
});

it('shows a user only their own vouchers unless they hold the company or all permission', function () {
    $mine = ($this->makeVoucher)(['party_name' => 'Mine', 'status' => 'draft']);
    $colleague = User::factory()->create(['supplier_id' => $this->supplier->id])->givePermissionTo(['expense-voucher-list', 'expense-voucher-create']);
    $this->actingAs($colleague);
    $theirs = ExpenseVoucher::factory()->create(['supplier_id' => $this->supplier->id, 'expense_account_id' => $this->ledger['5221']->id, 'party_name' => 'Theirs']);
    $this->actingAs(User::factory()->create(['supplier_id' => Supplier::factory()->create()->id]));
    $elsewhere = ExpenseVoucher::factory()->create(['party_name' => 'Elsewhere', 'expense_account_id' => $this->ledger['5221']->id]);

    $parties = fn (User $user) => $this->actingAs($user)->get(route('expense-vouchers.index'))->viewData('vouchers')->pluck('party_name')->sort()->values()->all();

    expect($parties($this->maker))->toBe(['Mine'])
        ->and($parties($colleague))->toBe(['Theirs'])
        ->and($parties($this->checker))->toBe(['Mine', 'Theirs']);

    $this->actingAs($this->maker)->get(route('expense-vouchers.show', $theirs))->assertNotFound();

    Permission::findOrCreate('expense-voucher-view-all');
    expect($parties($this->maker->givePermissionTo('expense-voucher-view-all')))->toBe(['Elsewhere', 'Mine', 'Theirs']);
    expect($parties(User::factory()->create(['is_super_admin' => 'Yes'])->givePermissionTo('expense-voucher-list')))->toBe(['Elsewhere', 'Mine', 'Theirs']);
});

it('rolls the whole approval back when posting fails, so no voucher is half done', function () {
    $voucher = ($this->makeVoucher)(['payment_type' => 'advance', 'payee_type' => 'employee', 'employee_id' => Employee::factory()->create(['supplier_id' => $this->supplier->id])->id, 'expense_vendor_id' => null]);
    $this->ledger['1131']->delete();

    $this->actingAs($this->checker)->post(route('expense-vouchers.approve', $voucher))->assertSessionHas('error');

    expect($voucher->fresh()->status)->toBe('pending')->and(EmployeeAdvance::count())->toBe(0);
});

it('lets the service count a voucher visible only to its creator, the company, or an admin', function () {
    $voucher = ($this->makeVoucher)(['status' => 'draft']);

    expect($voucher->canBeSeenBy($this->maker))->toBeTrue()
        ->and($voucher->canBeSeenBy($this->checker))->toBeTrue()
        ->and($voucher->canBeSeenBy(User::factory()->create(['supplier_id' => Supplier::factory()->create()->id])))->toBeFalse();
});

it('uses the maker-checker service constants for its accounts', function () {
    expect(ExpenseVoucherService::PAYABLES_ACCOUNT)->toBe('2113')->and(ExpenseVoucherService::ADVANCES_ACCOUNT)->toBe('1131');
});
