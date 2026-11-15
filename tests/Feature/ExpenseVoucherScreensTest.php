<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\ExpenseVoucher;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\ExpenseVoucherService;
use App\Support\CashNotes;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['list', 'create', 'edit', 'approve', 'print', 'reverse', 'view-company'] as $action) {
        Permission::findOrCreate('expense-voucher-'.$action);
    }

    $this->ledger = seedExpenseLedger();
    $this->company = Company::first() ?? Company::factory()->create(['company_name' => 'Moon Traders', 'abbr' => 'MT']);
    $this->supplier = Supplier::factory()->create(['short_name' => 'NFL']);

    $this->maker = User::factory()->create(['supplier_id' => $this->supplier->id])
        ->givePermissionTo(['expense-voucher-list', 'expense-voucher-create', 'expense-voucher-edit']);
    $this->checker = User::factory()->create(['supplier_id' => $this->supplier->id])
        ->givePermissionTo(['expense-voucher-list', 'expense-voucher-approve', 'expense-voucher-print', 'expense-voucher-view-company']);

    $this->payload = [
        'payee_type' => 'other',
        'party_name' => 'Ali Tea Stall',
        'expense_category_id' => ExpenseCategory::factory()->create()->id,
        'expense_account_id' => $this->ledger['5221']->id,
        'voucher_date' => now()->toDateString(),
        'owner' => 'supplier:'.$this->supplier->id,
        'amount' => 1500,
        'payment_method' => 'cash',
    ];

    $this->approved = function (array $attributes = []): ExpenseVoucher {
        $voucher = ExpenseVoucher::factory()->pending()->create($attributes + [
            'supplier_id' => $this->supplier->id,
            'expense_account_id' => $this->ledger['5221']->id,
            'payment_type' => ExpenseVoucher::TYPE_CASH,
            'payment_method' => ExpenseVoucher::METHOD_CASH,
            'party_name' => 'Ali Tea Stall',
            'amount' => 1500,
        ]);
        $voucher->forceFill(['created_by' => $this->maker->id])->save();

        return app(ExpenseVoucherService::class)->approve($voucher, $this->checker, CashNotes::fromInput(['1000' => 1, '500' => 1]));
    };
});

it('shows the reverse button only with the permission', function () {
    $voucher = ($this->approved)();
    $reverseUrl = route('expense-vouchers.reverse', $voucher);

    $this->actingAs($this->checker)->get(route('expense-vouchers.show', $voucher))->assertOk()->assertDontSee($reverseUrl, false);

    $this->checker->givePermissionTo('expense-voucher-reverse');
    $this->actingAs($this->checker->fresh())->get(route('expense-vouchers.show', $voucher))->assertOk()
        ->assertSee($reverseUrl, false)
        ->assertSee('name="password"', false);
});

it('shows a reversed banner and no reverse button once reversed', function () {
    $voucher = ($this->approved)();
    $voucher->update(['status' => ExpenseVoucher::STATUS_REVERSED, 'reversed_at' => now(), 'reversed_by' => $this->checker->id, 'reversal_reason' => 'Entered twice by mistake']);
    $this->checker->givePermissionTo('expense-voucher-reverse');

    $this->actingAs($this->checker->fresh())->get(route('expense-vouchers.show', $voucher))->assertOk()
        ->assertSee('Entered twice by mistake')
        ->assertDontSee(route('expense-vouchers.reverse', $voucher), false);
});

it('prints an A4 voucher with the amount in words', function () {
    $voucher = ($this->approved)();

    $this->actingAs($this->checker)->get(route('expense-vouchers.print', $voucher))->assertOk()
        ->assertSee($this->company->company_name)
        ->assertSee('Cash Payment Voucher')
        ->assertSee('one thousand five hundred rupees only')
        ->assertSee('Received by')
        ->assertDontSee('DUPLICATE');

    expect($voucher->fresh()->printed_at)->not->toBeNull();

    $this->actingAs($this->checker)->get(route('expense-vouchers.print', $voucher))->assertOk()->assertSee('DUPLICATE');
    $this->actingAs($this->checker)->get(route('expense-vouchers.print', ['expense_voucher' => $voucher, 'format' => 'slip']))->assertOk()->assertSee('80mm', false);
});

it('keeps the rejection reason after resubmitting', function () {
    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), ['action' => 'submit'] + $this->payload)->assertSessionHasNoErrors();
    $voucher = ExpenseVoucher::first();

    $this->actingAs($this->checker)->post(route('expense-vouchers.reject', $voucher), ['rejection_reason' => 'No receipt attached'])->assertRedirect();
    $this->actingAs($this->maker)->put(route('expense-vouchers.update', $voucher), ['action' => 'submit'] + $this->payload)->assertSessionHasNoErrors();

    expect($voucher->fresh())
        ->status->toBe(ExpenseVoucher::STATUS_PENDING)
        ->rejection_reason->toBe('No receipt attached');
});

it('shows a friendly message for a posting failure', function () {
    $voucher = ExpenseVoucher::factory()->pending()->create(['supplier_id' => $this->supplier->id, 'expense_account_id' => $this->ledger['5221']->id, 'amount' => 1500, 'payment_method' => 'credit', 'payment_type' => 'credit']);
    $voucher->forceFill(['created_by' => $this->maker->id])->save();

    $this->mock(AccountingService::class, fn ($mock) => $mock->shouldReceive('createJournalEntry')
        ->andReturn(['success' => false, 'message' => 'SQLSTATE[23505]: Unique violation']));

    $this->actingAs($this->checker)->followingRedirects()->post(route('expense-vouchers.approve', $voucher))->assertOk()
        ->assertSee('Posting failed. Please contact the admin.')
        ->assertDontSee('SQLSTATE');

    expect($voucher->fresh()->status)->toBe(ExpenseVoucher::STATUS_PENDING);
});

it('uses plain names in validation messages', function () {
    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), ['amount' => 99999999999999] + $this->payload)
        ->assertSessionHasErrors(['amount' => 'Amount is too large.']);

    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), ['payment_method' => 'cheque'] + $this->payload)
        ->assertSessionHasErrors(['bank_account_id' => 'The bank account field is required.', 'cheque_number' => 'The cheque number field is required.']);
});

it('offers the payment method, the banks and employees with their company on the form', function () {
    $this->actingAs($this->maker)->get(route('expense-vouchers.create'))->assertOk()
        ->assertSee('name="payment_method"', false)
        ->assertDontSee('name="payment_type"', false)
        ->assertSee('Credit, pay later')
        ->assertSee('name="bank_account_id"', false)
        ->assertDontSee('name="denom[5000]"', false);

    Employee::factory()->create(['name' => 'Bilal Khan', 'supplier_id' => $this->supplier->id, 'is_active' => true]);

    $this->actingAs($this->maker)->getJson(route('expense-vouchers.employees.search', ['q' => 'Bilal']))->assertOk()
        ->assertJsonPath('results.0.text', 'Bilal Khan · NFL');
});

it('has a reversed tab with its count on the list', function () {
    $voucher = ($this->approved)();
    $voucher->update(['status' => ExpenseVoucher::STATUS_REVERSED]);

    $this->actingAs($this->checker)->get(route('expense-vouchers.index'))->assertOk()
        ->assertSee('filter%5Bstatus%5D=reversed', false);
});

it('asks the checker, not the maker, for the notes of a cash voucher', function () {
    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), ['action' => 'submit'] + $this->payload)->assertSessionHasNoErrors();
    $voucher = ExpenseVoucher::first();

    expect($voucher->status)->toBe(ExpenseVoucher::STATUS_PENDING)->and($voucher->cashPayout)->toBeNull();

    $this->actingAs($this->checker)->get(route('expense-vouchers.show', $voucher))->assertOk()
        ->assertSee('Count the notes and approve')
        ->assertSee('name="denom[5000]"', false);
});

it('refuses to approve a cash voucher without the notes or with notes that do not add up', function () {
    $voucher = ExpenseVoucher::factory()->pending()->create(['supplier_id' => $this->supplier->id, 'expense_account_id' => $this->ledger['5221']->id, 'amount' => 1500, 'payment_method' => 'cash']);
    $voucher->forceFill(['created_by' => $this->maker->id])->save();

    $this->actingAs($this->checker)->post(route('expense-vouchers.approve', $voucher))->assertSessionHasErrors('denom');
    $this->actingAs($this->checker)->post(route('expense-vouchers.approve', $voucher), ['denom' => ['1000' => 1]])
        ->assertSessionHasErrors(['denom' => 'The notes paid out add up to Rs 1,000.00 but the voucher is Rs 1,500.00.']);

    expect($voucher->fresh()->status)->toBe(ExpenseVoucher::STATUS_PENDING)
        ->and($voucher->fresh()->journal_entry_id)->toBeNull()
        ->and($voucher->cashPayout()->exists())->toBeFalse()
        ->and(fn () => app(ExpenseVoucherService::class)->approve($voucher, $this->checker))->toThrow(RuntimeException::class, 'Count the notes paid out');
});

it('saves the notes the checker paid out when approving a cash voucher', function () {
    $voucher = ExpenseVoucher::factory()->pending()->create(['supplier_id' => $this->supplier->id, 'expense_account_id' => $this->ledger['5221']->id, 'amount' => 1505, 'payment_method' => 'cash']);
    $voucher->forceFill(['created_by' => $this->maker->id])->save();

    $this->actingAs($this->checker)->post(route('expense-vouchers.approve', $voucher), ['denom' => ['1000' => 1, '500' => 1, 'coins' => 5]])
        ->assertSessionHas('success');

    $voucher->refresh();
    expect($voucher->status)->toBe(ExpenseVoucher::STATUS_APPROVED)
        ->and($voucher->cashPayout->denom_1000)->toBe(1)
        ->and($voucher->cashPayout->denom_500)->toBe(1)
        ->and((float) $voucher->cashPayout->total_amount)->toBe(1505.0);

    $this->actingAs($this->checker)->get(route('expense-vouchers.show', $voucher))->assertOk()
        ->assertSee('Notes paid out')
        ->assertSee('Counted and handed over by '.$this->checker->name);
});

it('approves a credit voucher without notes', function () {
    $voucher = ExpenseVoucher::factory()->pending()->create(['supplier_id' => $this->supplier->id, 'expense_account_id' => $this->ledger['5221']->id, 'amount' => 1500, 'payment_method' => 'credit', 'payment_type' => 'credit']);
    $voucher->forceFill(['created_by' => $this->maker->id])->save();

    $this->actingAs($this->checker)->post(route('expense-vouchers.approve', $voucher))->assertSessionHas('success');

    expect($voucher->fresh()->status)->toBe(ExpenseVoucher::STATUS_APPROVED)->and($voucher->cashPayout()->exists())->toBeFalse();
});
