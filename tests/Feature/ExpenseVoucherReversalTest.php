<?php

use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\ExpenseVendor;
use App\Models\ExpenseVendorPayment;
use App\Models\ExpenseVendorPaymentAllocation;
use App\Models\ExpenseVoucher;
use App\Models\JournalEntry;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ExpenseVoucherService;
use App\Support\CashNotes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['expense-voucher-list', 'expense-voucher-approve', 'expense-voucher-reverse', 'expense-voucher-view-company'] as $name) {
        Permission::findOrCreate($name);
    }

    $this->ledger = seedExpenseLedger();
    $this->supplier = Supplier::factory()->create();

    $this->maker = User::factory()->create(['supplier_id' => $this->supplier->id, 'password' => 'secret-pass']);
    $this->checker = User::factory()->create(['supplier_id' => $this->supplier->id]);
    $this->reverser = User::factory()->create(['supplier_id' => $this->supplier->id, 'password' => 'secret-pass'])
        ->givePermissionTo(['expense-voucher-list', 'expense-voucher-reverse', 'expense-voucher-view-company']);

    $this->approved = function (array $attributes = []): ExpenseVoucher {
        $voucher = ExpenseVoucher::factory()->pending()->create($attributes + [
            'supplier_id' => $this->supplier->id,
            'expense_account_id' => $this->ledger['5221']->id,
            'payment_type' => ExpenseVoucher::TYPE_CASH,
            'payment_method' => ExpenseVoucher::METHOD_CASH,
            'amount' => 1500,
        ]);
        $voucher->forceFill(['created_by' => $this->maker->id])->save();

        $notes = $voucher->payment_method === ExpenseVoucher::METHOD_CASH ? CashNotes::fromInput(['coins' => (float) $voucher->amount]) : null;

        return app(ExpenseVoucherService::class)->approve($voucher, $this->checker, $notes);
    };

    $this->reverse = fn (ExpenseVoucher $voucher, ?User $by = null, array $data = []) => $this->actingAs($by ?? $this->reverser)
        ->post(route('expense-vouchers.reverse', $voucher), $data + ['reason' => 'Entered twice by mistake', 'password' => 'secret-pass']);

    $this->netOf = fn (ExpenseVoucher $voucher, string $code) => (float) DB::table('journal_entry_details as d')
        ->join('journal_entries as e', 'e.id', '=', 'd.journal_entry_id')
        ->where('d.chart_of_account_id', $this->ledger[$code]->id)
        ->where(fn ($q) => $q->where('e.id', $voucher->journal_entry_id)->orWhere('e.reverses_entry_id', $voucher->journal_entry_id))
        ->selectRaw('coalesce(sum(d.debit - d.credit), 0) as net')->value('net');
});

it('reverses an approved cash voucher with reason and password', function () {
    $voucher = ($this->approved)();

    ($this->reverse)($voucher)->assertSessionHas('success');

    $voucher->refresh();
    $reversal = JournalEntry::where('reverses_entry_id', $voucher->journal_entry_id)->first();

    expect($voucher->status)->toBe(ExpenseVoucher::STATUS_REVERSED)
        ->and($voucher->reversed_by)->toBe($this->reverser->id)
        ->and($voucher->reversal_reason)->toBe('Entered twice by mistake')
        ->and($voucher->reversed_at)->not->toBeNull()
        ->and($voucher->reversal_journal_entry_id)->toBe($reversal->id)
        ->and($reversal->reference)->toStartWith('REV-')
        ->and($reversal->description)->toBe('REVERSAL: expense voucher '.$voucher->voucher_number.' - Entered twice by mistake')
        ->and(($this->netOf)($voucher, '1121'))->toBe(0.0)
        ->and($voucher->cashPayout()->exists())->toBeTrue();
});

it('refuses without the permission', function () {
    $voucher = ($this->approved)();
    $nobody = User::factory()->create(['supplier_id' => $this->supplier->id, 'password' => 'secret-pass']);

    ($this->reverse)($voucher, $nobody)->assertForbidden();

    expect($voucher->fresh()->status)->toBe(ExpenseVoucher::STATUS_APPROVED);
});

it('refuses a wrong password', function () {
    $voucher = ($this->approved)();

    ($this->reverse)($voucher, null, ['password' => 'wrong'])->assertSessionHas('error');

    expect($voucher->fresh()->status)->toBe(ExpenseVoucher::STATUS_APPROVED)
        ->and(JournalEntry::where('reverses_entry_id', $voucher->journal_entry_id)->exists())->toBeFalse();
});

it('needs a reason of at least five characters', function () {
    $voucher = ($this->approved)();

    ($this->reverse)($voucher, null, ['reason' => 'no'])->assertSessionHasErrors('reason');

    expect($voucher->fresh()->status)->toBe(ExpenseVoucher::STATUS_APPROVED);
});

it('refuses the maker', function () {
    $voucher = ($this->approved)();
    $this->maker->givePermissionTo(['expense-voucher-list', 'expense-voucher-reverse', 'expense-voucher-view-company']);

    ($this->reverse)($voucher, $this->maker)->assertSessionHas('error', 'The maker of a voucher cannot also reverse it.');

    expect($voucher->fresh()->status)->toBe(ExpenseVoucher::STATUS_APPROVED);
});

it('refuses a credit voucher that is partly paid', function () {
    $voucher = ($this->approved)([
        'payment_type' => ExpenseVoucher::TYPE_CREDIT, 'payment_method' => ExpenseVoucher::METHOD_CREDIT,
        'expense_vendor_id' => ExpenseVendor::factory()->create()->id,
    ]);
    $voucher->update(['paid_amount' => 500, 'payment_status' => ExpenseVoucher::PARTIAL]);

    ($this->reverse)($voucher)->assertSessionHas('error', 'This credit voucher has already been paid in part; it cannot be reversed.');

    expect($voucher->fresh()->status)->toBe(ExpenseVoucher::STATUS_APPROVED);
});

it('refuses a credit voucher that has a payment allocation of any status', function () {
    $vendor = ExpenseVendor::factory()->create();
    $voucher = ($this->approved)([
        'payment_type' => ExpenseVoucher::TYPE_CREDIT, 'payment_method' => ExpenseVoucher::METHOD_CREDIT,
        'expense_vendor_id' => $vendor->id,
    ]);
    $payment = ExpenseVendorPayment::unguarded(fn () => ExpenseVendorPayment::create([
        'uuid' => (string) Str::uuid(), 'payment_number' => 'VP-1', 'payment_date' => now()->toDateString(),
        'expense_vendor_id' => $vendor->id, 'method' => 'cash', 'amount' => 100, 'status' => 'pending',
    ]));
    ExpenseVendorPaymentAllocation::create(['expense_vendor_payment_id' => $payment->id, 'expense_voucher_id' => $voucher->id, 'amount' => 100]);

    ($this->reverse)($voucher)->assertSessionHas('error');

    expect($voucher->fresh()->status)->toBe(ExpenseVoucher::STATUS_APPROVED);
});

it('refuses an advance that was recovered', function () {
    $employee = Employee::factory()->create(['supplier_id' => $this->supplier->id]);
    $voucher = ($this->approved)([
        'payment_type' => ExpenseVoucher::TYPE_ADVANCE, 'employee_id' => $employee->id,
        'expense_account_id' => $this->ledger['1131']->id,
    ]);
    $voucher->advance->update(['recovered_amount' => 300]);

    ($this->reverse)($voucher)->assertSessionHas('error', 'This advance has already been recovered in payroll; it cannot be reversed.');

    expect($voucher->fresh()->status)->toBe(ExpenseVoucher::STATUS_APPROVED);
});

it('cancels the advance when an unrecovered advance voucher is reversed', function () {
    $employee = Employee::factory()->create(['supplier_id' => $this->supplier->id]);
    $voucher = ($this->approved)([
        'payment_type' => ExpenseVoucher::TYPE_ADVANCE, 'employee_id' => $employee->id,
        'expense_account_id' => $this->ledger['1131']->id,
    ]);

    ($this->reverse)($voucher)->assertSessionHas('success');

    expect($voucher->advance->fresh()->status)->toBe(EmployeeAdvance::STATUS_CANCELLED)
        ->and(($this->netOf)($voucher, '1131'))->toBe(0.0);
});

it('reverses only once when posted twice', function () {
    $voucher = ($this->approved)();

    ($this->reverse)($voucher)->assertSessionHas('success');
    ($this->reverse)($voucher)->assertSessionHas('error');

    expect(JournalEntry::where('reverses_entry_id', $voucher->journal_entry_id)->count())->toBe(1)
        ->and(($this->netOf)($voucher, '1121'))->toBe(0.0);
});

it('refuses a voucher that is not approved', function () {
    $voucher = ExpenseVoucher::factory()->pending()->create(['supplier_id' => $this->supplier->id]);

    ($this->reverse)($voucher)->assertSessionHas('error', 'Only an approved voucher can be reversed.');
});
