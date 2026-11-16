<?php

use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\ExpenseCategory;
use App\Models\ExpenseVendor;
use App\Models\ExpenseVoucher;
use App\Models\ExpenseVoucherLine;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['expense-voucher-list', 'expense-voucher-create', 'expense-voucher-edit', 'expense-voucher-approve', 'expense-voucher-reverse', 'expense-voucher-view-company'] as $name) {
        Permission::findOrCreate($name);
    }

    $this->ledger = seedExpenseLedger();
    $this->supplier = Supplier::factory()->create();
    $this->vendor = ExpenseVendor::factory()->create(['name' => 'Hafeez Stationers']);
    $this->category = ExpenseCategory::factory()->create(['name' => 'Toner', 'default_expense_account_id' => $this->ledger['5221']->id]);

    // One password hash for both, so switching users between requests keeps the session's password check happy.
    $password = Hash::make('secret-pass');
    $this->maker = User::factory()->create(['supplier_id' => $this->supplier->id, 'password' => $password])
        ->givePermissionTo(['expense-voucher-list', 'expense-voucher-create', 'expense-voucher-edit']);
    $this->checker = User::factory()->create(['supplier_id' => $this->supplier->id, 'password' => $password])
        ->givePermissionTo(['expense-voucher-list', 'expense-voucher-approve', 'expense-voucher-reverse', 'expense-voucher-view-company']);

    /** One shop bill: toner 2,500, a repair 1,800 and tea 450. */
    $this->threeLines = [
        ['expense_category_id' => $this->category->id, 'expense_account_id' => $this->ledger['5221']->id, 'description' => 'Toner', 'amount' => '2500'],
        ['expense_account_id' => $this->ledger['5281']->id, 'description' => 'Printer repair', 'amount' => '1800'],
        ['expense_account_id' => $this->ledger['5293']->id, 'description' => 'Tea', 'amount' => '450.00'],
    ];

    $this->payload = fn (array $overrides = []) => $overrides + [
        'action' => 'submit',
        'payee_type' => 'vendor',
        'expense_vendor_id' => $this->vendor->id,
        'vendor_bill_no' => 'B-778',
        'vendor_bill_date' => now()->subDay()->toDateString(),
        'voucher_date' => now()->toDateString(),
        'owner' => 'supplier:'.$this->supplier->id,
        'payment_method' => 'cash',
        'narration' => 'Office bill',
        'lines' => $this->threeLines,
    ];

    $this->store = fn (array $overrides = []) => $this->actingAs($this->maker)->post(route('expense-vouchers.store'), ($this->payload)($overrides));

    $this->approve = fn (ExpenseVoucher $voucher, array $data = []) => $this->actingAs($this->checker)
        ->post(route('expense-vouchers.approve', $voucher), $data + ['approve_without_attachment' => 1]);

    $this->journalLines = fn (int $entryId) => DB::table('journal_entry_details')->where('journal_entry_id', $entryId)->orderBy('id')->get();
});

it('stores three bill lines and sums them into the voucher amount', function () {
    ($this->store)(['amount' => '1'])->assertSessionHasNoErrors()->assertRedirect();

    $voucher = ExpenseVoucher::sole();
    expect((float) $voucher->amount)->toBe(4750.0)
        ->and($voucher->expense_account_id)->toBe($this->ledger['5221']->id)
        ->and($voucher->expense_category_id)->toBe($this->category->id)
        ->and($voucher->vendor_bill_date->toDateString())->toBe(now()->subDay()->toDateString())
        ->and($voucher->lines)->toHaveCount(3)
        ->and($voucher->lines->pluck('line_no')->all())->toBe([1, 2, 3])
        ->and($voucher->lines->pluck('description')->all())->toBe(['Toner', 'Printer repair', 'Tea'])
        ->and($voucher->lines->map(fn ($line) => (float) $line->amount)->all())->toBe([2500.0, 1800.0, 450.0]);
});

it('posts one debit per line and a single credit that balance on approval', function () {
    ($this->store)(['payment_method' => 'credit']);
    $voucher = ExpenseVoucher::sole();

    ($this->approve)($voucher)->assertSessionHas('success');

    $voucher->refresh();
    $lines = ($this->journalLines)($voucher->journal_entry_id);
    $debits = $lines->where('debit', '>', 0);
    $credits = $lines->where('credit', '>', 0);

    expect($voucher->status)->toBe('approved')
        ->and($debits)->toHaveCount(3)
        ->and($credits)->toHaveCount(1)
        ->and($debits->pluck('chart_of_account_id')->values()->all())->toBe([$this->ledger['5221']->id, $this->ledger['5281']->id, $this->ledger['5293']->id])
        ->and($debits->pluck('description')->values()->all())->toBe(['Toner', 'Printer repair', 'Tea'])
        ->and((float) $credits->first()->credit)->toBe(4750.0)
        ->and($credits->first()->chart_of_account_id)->toBe($this->ledger['2113']->id)
        ->and(round((float) $lines->sum('debit'), 2))->toBe(round((float) $lines->sum('credit'), 2))
        ->and($voucher->outstanding())->toBe(4750.0);
});

it('needs cash notes equal to the bill total to approve a cash voucher', function () {
    ($this->store)();
    $voucher = ExpenseVoucher::sole();

    ($this->approve)($voucher, ['denom' => ['1000' => 2, '500' => 1]])->assertSessionHasErrors('denom');
    expect($voucher->fresh()->status)->toBe('pending');

    ($this->approve)($voucher, ['denom' => ['1000' => 4, '500' => 1, '100' => 2, '50' => 1]])->assertSessionHas('success');

    $credit = ($this->journalLines)($voucher->fresh()->journal_entry_id)->firstWhere('credit', '>', 0);
    expect($voucher->fresh()->status)->toBe('approved')
        ->and($credit->chart_of_account_id)->toBe($this->ledger['1121']->id)
        ->and((float) $credit->credit)->toBe(4750.0);
});

it('refuses a line booked to an account that is not an expense account', function () {
    $lines = $this->threeLines;
    $lines[1]['expense_account_id'] = $this->ledger['1171']->id;

    ($this->store)(['lines' => $lines])->assertSessionHasErrors('lines.1.expense_account_id');

    expect(ExpenseVoucher::count())->toBe(0);
});

it('still accepts the single-line fields and makes one line of them', function () {
    $payload = ($this->payload)(['amount' => '1200', 'expense_category_id' => $this->category->id, 'expense_account_id' => $this->ledger['5221']->id]);
    unset($payload['lines']);

    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();

    $voucher = ExpenseVoucher::sole();
    $line = $voucher->lines->sole();
    expect((float) $voucher->amount)->toBe(1200.0)
        ->and($line->line_no)->toBe(1)
        ->and($line->expense_account_id)->toBe($this->ledger['5221']->id)
        ->and($line->expense_category_id)->toBe($this->category->id)
        ->and((float) $line->amount)->toBe(1200.0);
});

it('ignores bill lines on an advance and posts it to employee advances', function () {
    $employee = Employee::factory()->create(['supplier_id' => $this->supplier->id, 'is_active' => true]);

    ($this->store)(['payee_type' => 'employee', 'employee_id' => $employee->id, 'expense_vendor_id' => null, 'amount' => '3000'])->assertSessionHasNoErrors();

    $voucher = ExpenseVoucher::sole();
    expect($voucher->payment_type)->toBe('advance')
        ->and((float) $voucher->amount)->toBe(3000.0)
        ->and($voucher->lines)->toHaveCount(1)
        ->and($voucher->lines->first()->expense_account_id)->toBe($this->ledger['1131']->id);

    ($this->approve)($voucher, ['denom' => ['1000' => 3]])->assertSessionHas('success');

    $lines = ($this->journalLines)($voucher->fresh()->journal_entry_id);
    expect($lines)->toHaveCount(2)
        ->and($lines->firstWhere('debit', '>', 0)->chart_of_account_id)->toBe($this->ledger['1131']->id)
        ->and((float) $lines->firstWhere('debit', '>', 0)->debit)->toBe(3000.0)
        ->and(EmployeeAdvance::sole()->amount)->toEqual(3000);
});

it('replaces the lines when a draft is edited', function () {
    ($this->store)(['action' => 'draft']);
    $voucher = ExpenseVoucher::sole();

    $this->actingAs($this->maker)->put(route('expense-vouchers.update', $voucher), ($this->payload)([
        'action' => 'draft',
        'lines' => [
            ['expense_account_id' => $this->ledger['5293']->id, 'description' => 'Tea and biscuits', 'amount' => '600'],
            ['expense_account_id' => $this->ledger['5221']->id, 'description' => 'Paper', 'amount' => '900.50'],
        ],
    ]))->assertSessionHasNoErrors()->assertRedirect();

    $voucher->refresh();
    expect((float) $voucher->amount)->toBe(1500.5)
        ->and($voucher->expense_account_id)->toBe($this->ledger['5293']->id)
        ->and($voucher->expense_category_id)->toBeNull()
        ->and(ExpenseVoucherLine::where('expense_voucher_id', $voucher->id)->count())->toBe(2)
        ->and($voucher->lines->pluck('description')->all())->toBe(['Tea and biscuits', 'Paper']);
});

it('reverses every line of a three-line voucher', function () {
    ($this->store)(['payment_method' => 'credit']);
    $voucher = ExpenseVoucher::sole();
    ($this->approve)($voucher);

    $this->actingAs($this->checker)->post(route('expense-vouchers.reverse', $voucher), ['reason' => 'Bill entered twice', 'password' => 'secret-pass'])->assertSessionHas('success');

    $voucher->refresh();
    $net = fn (string $code) => (float) DB::table('journal_entry_details')
        ->whereIn('journal_entry_id', [$voucher->journal_entry_id, $voucher->reversal_journal_entry_id])
        ->where('chart_of_account_id', $this->ledger[$code]->id)
        ->selectRaw('coalesce(sum(debit - credit), 0) as net')->value('net');

    expect($voucher->status)->toBe('reversed')
        ->and(($this->journalLines)($voucher->reversal_journal_entry_id))->toHaveCount(4)
        ->and($net('5221'))->toBe(0.0)
        ->and($net('5281'))->toBe(0.0)
        ->and($net('5293'))->toBe(0.0)
        ->and($net('2113'))->toBe(0.0);
});

it('gives every voucher made before bill lines exactly one line, once', function () {
    $this->actingAs($this->maker);
    $vehicleId = Vehicle::factory()->create()->id;
    $voucher = ExpenseVoucher::factory()->create([
        'supplier_id' => $this->supplier->id, 'expense_account_id' => $this->ledger['5221']->id, 'expense_category_id' => $this->category->id,
        'vehicle_id' => $vehicleId, 'amount' => 777.25, 'narration' => 'Old voucher',
    ]);

    expect(ExpenseVoucherLine::backfillMissing())->toBe(1)
        ->and(ExpenseVoucherLine::backfillMissing())->toBe(0);

    $line = $voucher->lines()->sole();
    expect($line->line_no)->toBe(1)
        ->and($line->expense_account_id)->toBe($this->ledger['5221']->id)
        ->and($line->expense_category_id)->toBe($this->category->id)
        ->and($line->vehicle_id)->toBe($vehicleId)
        ->and($line->description)->toBe('Old voucher')
        ->and((float) $line->amount)->toBe(777.25);
});

it('refuses more than thirty lines', function () {
    $lines = array_fill(0, 31, ['expense_account_id' => $this->ledger['5221']->id, 'description' => 'Item', 'amount' => '10']);

    ($this->store)(['lines' => $lines])->assertSessionHasErrors('lines');

    expect(ExpenseVoucher::count())->toBe(0);
});

it('lists every bill line on the voucher page and the extra lines on the list', function () {
    ($this->store)();
    $voucher = ExpenseVoucher::sole();

    $this->actingAs($this->checker)->get(route('expense-vouchers.show', $voucher))->assertOk()
        ->assertSeeInOrder(['Particulars', 'Toner', '2,500.00', 'Printer repair', '1,800.00', 'Tea', '450.00', 'Total', '4,750.00']);

    $this->actingAs($this->checker)->get(route('expense-vouchers.index'))->assertOk()->assertSee('+2 more');

    $voucher->update(['status' => 'draft']);
    $this->actingAs($this->maker)->get(route('expense-vouchers.edit', $voucher))->assertOk()->assertSee('Printer repair', false);
});

it('totals the summary by each line\'s category, not the first line\'s', function () {
    $repair = ExpenseCategory::factory()->create(['name' => 'Repair', 'default_expense_account_id' => $this->ledger['5281']->id]);
    ($this->store)(['payment_method' => 'credit', 'lines' => [
        ['expense_category_id' => $this->category->id, 'expense_account_id' => $this->ledger['5221']->id, 'description' => 'Toner', 'amount' => '2500'],
        ['expense_category_id' => $repair->id, 'expense_account_id' => $this->ledger['5281']->id, 'description' => 'Repair', 'amount' => '1800'],
    ]]);
    ($this->approve)(ExpenseVoucher::sole())->assertSessionHas('success');

    $response = $this->actingAs($this->checker)->get(route('expense-vouchers.summary'))->assertOk();
    $byCategory = $response->viewData('byCategory')->keyBy('label');

    expect((float) $byCategory['Toner']->total)->toBe(2500.0)
        ->and((float) $byCategory['Repair']->total)->toBe(1800.0)
        ->and((int) $byCategory['Toner']->vouchers)->toBe(1)
        ->and((float) $byCategory['Toner']->owed + (float) $byCategory['Repair']->owed)->toBe(4300.0)
        ->and((float) $response->viewData('grand')->total)->toBe(4300.0)
        ->and((float) $byCategory->sum('total'))->toBe(4300.0);
});

it('lists every bill line on the 80mm slip', function () {
    Permission::findOrCreate('expense-voucher-print');
    $this->checker->givePermissionTo('expense-voucher-print');
    ($this->store)(['payment_method' => 'credit']);
    $voucher = ExpenseVoucher::sole();
    ($this->approve)($voucher);

    $this->actingAs($this->checker)->get(route('expense-vouchers.print', [$voucher, 'format' => 'slip', 'autoprint' => 0]))->assertOk()
        ->assertSeeInOrder(['Miscellaneous Expenses Rs 2,500.00', 'Salary Rs 1,800.00', 'Short Amount Rs 450.00']);
});
