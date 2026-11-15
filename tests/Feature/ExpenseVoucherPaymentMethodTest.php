<?php

use App\Models\BankAccount;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\ExpenseVoucher;
use App\Models\JournalEntry;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ExpenseVoucherService;
use App\Support\CashNotes;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['expense-voucher-list', 'expense-voucher-create', 'expense-voucher-edit', 'expense-voucher-approve', 'expense-voucher-view-company'] as $name) {
        Permission::findOrCreate($name);
    }

    $this->ledger = seedExpenseLedger();
    $this->supplier = Supplier::factory()->create();
    $this->bank = BankAccount::factory()->create(['chart_of_account_id' => $this->ledger['1171']->id]);

    $this->maker = User::factory()->create(['supplier_id' => $this->supplier->id])->givePermissionTo(['expense-voucher-list', 'expense-voucher-create', 'expense-voucher-edit']);
    $this->checker = User::factory()->create(['supplier_id' => $this->supplier->id])->givePermissionTo(['expense-voucher-list', 'expense-voucher-approve', 'expense-voucher-view-company']);

    $this->payload = [
        'action' => 'submit',
        'payee_type' => 'other',
        'party_name' => 'Ali Tea Stall',
        'expense_category_id' => ExpenseCategory::factory()->create()->id,
        'expense_account_id' => $this->ledger['5221']->id,
        'voucher_date' => now()->toDateString(),
        'owner' => 'supplier:'.$this->supplier->id,
        'amount' => 1500,
    ];

    $this->creditLineAccount = fn (ExpenseVoucher $voucher) => (int) DB::table('journal_entry_details')
        ->where('journal_entry_id', $voucher->fresh()->journal_entry_id)->where('credit', '>', 0)->value('chart_of_account_id');
});

it('posts a bank transfer voucher to the bank account and saves no notes', function () {
    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), [
        'payment_method' => 'bank_transfer', 'bank_account_id' => $this->bank->id, 'transaction_reference' => 'IBFT-778',
    ] + $this->payload)->assertSessionHasNoErrors();

    $voucher = ExpenseVoucher::first();
    $this->actingAs($this->checker)->post(route('expense-vouchers.approve', $voucher), ['approve_without_attachment' => 1])->assertSessionHas('success');

    $voucher->refresh();
    expect($voucher->status)->toBe('approved')
        ->and($voucher->payment_type)->toBe('cash')
        ->and($voucher->transaction_reference)->toBe('IBFT-778')
        ->and(($this->creditLineAccount)($voucher))->toBe($this->ledger['1171']->id)
        ->and($voucher->paid_from_account_id)->toBe($this->ledger['1171']->id)
        ->and($voucher->cashPayout)->toBeNull();
});

it('posts a cash voucher to 1121 and stores the notes the checker paid out', function () {
    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), ['payment_method' => 'cash'] + $this->payload)->assertSessionHasNoErrors();

    $voucher = ExpenseVoucher::first();
    expect($voucher->cashPayout)->toBeNull();

    $this->actingAs($this->checker)->post(route('expense-vouchers.approve', $voucher), ['approve_without_attachment' => 1, 'denom' => ['1000' => 1, '500' => 1]])->assertSessionHas('success');

    $voucher->refresh();
    expect($voucher->cashPayout->denom_1000)->toBe(1)
        ->and($voucher->cashPayout->denom_500)->toBe(1)
        ->and((float) $voucher->cashPayout->total_amount)->toBe(1500.0)
        ->and(($this->creditLineAccount)($voucher))->toBe($this->ledger['1121']->id)
        ->and($voucher->paid_from_account_id)->toBe($this->ledger['1121']->id);
});

it('refuses to approve cash notes that do not add up, or no notes at all', function () {
    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), ['payment_method' => 'cash'] + $this->payload)->assertSessionHasNoErrors();
    $voucher = ExpenseVoucher::first();

    $this->actingAs($this->checker)->post(route('expense-vouchers.approve', $voucher), ['approve_without_attachment' => 1])->assertSessionHasErrors('denom');
    $this->actingAs($this->checker)->post(route('expense-vouchers.approve', $voucher), ['approve_without_attachment' => 1, 'denom' => ['1000' => 1]])
        ->assertSessionHasErrors(['denom' => 'The notes paid out add up to Rs 1,000.00 but the voucher is Rs 1,500.00.']);

    expect($voucher->fresh()->status)->toBe('pending')->and(JournalEntry::count())->toBe(0)->and($voucher->cashPayout()->exists())->toBeFalse();
});

it('refuses a non-expense account', function () {
    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), [
        'payment_method' => 'cash', 'denom' => ['1000' => 1, '500' => 1], 'expense_account_id' => $this->ledger['1121']->id,
    ] + $this->payload)->assertSessionHasErrors('expense_account_id');

    expect(ExpenseVoucher::count())->toBe(0);
});

it('posts one journal when approved twice', function () {
    $this->actingAs($this->maker);
    $voucher = ExpenseVoucher::factory()->pending()->create(['supplier_id' => $this->supplier->id, 'expense_account_id' => $this->ledger['5221']->id, 'amount' => 700]);
    $service = app(ExpenseVoucherService::class);
    $secondClick = ExpenseVoucher::find($voucher->id); // a second request that loaded the voucher while it was still pending

    $notes = CashNotes::fromInput(['500' => 1, '100' => 2]);
    $service->approve($voucher, $this->checker, $notes);

    expect(fn () => $service->approve($secondClick, $this->checker, $notes))->toThrow(RuntimeException::class, 'Only a voucher waiting for approval')
        ->and(JournalEntry::count())->toBe(1);
});

it('requires a bank account for a cheque voucher', function () {
    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), [
        'payment_method' => 'cheque', 'cheque_number' => '000123', 'cheque_date' => now()->toDateString(),
    ] + $this->payload)->assertSessionHasErrors('bank_account_id');

    $unlinked = BankAccount::factory()->create(['chart_of_account_id' => null]);
    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), [
        'payment_method' => 'cheque', 'bank_account_id' => $unlinked->id,
    ] + $this->payload)->assertSessionHasErrors(['bank_account_id', 'cheque_number', 'cheque_date']);

    expect(ExpenseVoucher::count())->toBe(0);
});

it('warns when cash in hand goes negative', function () {
    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), ['payment_method' => 'cash'] + $this->payload)->assertSessionHasNoErrors();

    $voucher = ExpenseVoucher::first();
    $this->actingAs($this->checker)->post(route('expense-vouchers.approve', $voucher), ['approve_without_attachment' => 1, 'denom' => ['1000' => 1, '500' => 1]])
        ->assertSessionHas('success')
        ->assertSessionHas('warning', 'Cash in hand for '.now()->toDateString().' goes negative (Rs -1,500.00).');

    expect($voucher->fresh()->status)->toBe('approved');
});

it('credits the bank for an advance paid by bank transfer', function () {
    $employee = Employee::factory()->create(['supplier_id' => $this->supplier->id, 'is_active' => true]);

    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), [
        'payee_type' => 'employee', 'employee_id' => $employee->id, 'voucher_date' => now()->toDateString(), 'amount' => 6000,
        'payment_method' => 'bank_transfer', 'bank_account_id' => $this->bank->id,
    ])->assertSessionHasNoErrors();

    $voucher = ExpenseVoucher::first();
    $this->actingAs($this->checker)->post(route('expense-vouchers.approve', $voucher), ['approve_without_attachment' => 1])->assertSessionHas('success');

    expect($voucher->fresh()->payment_type)->toBe('advance')
        ->and(($this->creditLineAccount)($voucher))->toBe($this->ledger['1171']->id);
});

it('refuses credit for a payee that is not a shop', function () {
    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), ['payment_method' => 'credit'] + $this->payload)->assertSessionHasErrors('payment_method');

    expect(ExpenseVoucher::count())->toBe(0);
});

it('saves no notes from the maker, and switches a draft from cash to bank transfer', function () {
    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), ['action' => 'draft', 'payment_method' => 'cash', 'denom' => ['1000' => 1, '500' => 1]] + $this->payload);
    $voucher = ExpenseVoucher::first();
    expect($voucher->cashPayout)->toBeNull();

    $this->actingAs($this->maker)->put(route('expense-vouchers.update', $voucher), [
        'action' => 'draft', 'payment_method' => 'bank_transfer', 'bank_account_id' => $this->bank->id,
    ] + $this->payload)->assertSessionHasNoErrors();

    $voucher->refresh();
    expect($voucher->payment_method)->toBe('bank_transfer')->and($voucher->cashPayout)->toBeNull();
});
