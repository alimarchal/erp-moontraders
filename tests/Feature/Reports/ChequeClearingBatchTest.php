<?php

use App\Models\AccountType;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\ChequeClearing;
use App\Models\Customer;
use App\Models\CustomerEmployeeAccount;
use App\Models\CustomerEmployeeAccountTransaction;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementCheque;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AccountingService;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->travelTo('2026-11-15 10:00:00');
    $this->ledger = seedExpenseLedger();
    $this->ledger['1112'] = ChartOfAccount::firstOrCreate(['account_code' => '1112'], [
        'account_type_id' => AccountType::where('type_name', 'Assets')->value('id'),
        'currency_id' => $this->ledger['1121']->currency_id,
        'account_name' => 'Debtors', 'normal_balance' => 'debit', 'is_active' => true,
    ]);

    Permission::findOrCreate('report-audit-cheque-register');
    Permission::findOrCreate('report-audit-cheque-register-manage');

    $this->supplier = Supplier::factory()->create(['supplier_name' => 'Kausar Oil']);
    $this->user = User::factory()->create(['supplier_id' => $this->supplier->id]);
    $this->user->givePermissionTo(['report-audit-cheque-register', 'report-audit-cheque-register-manage']);

    $this->employee = Employee::factory()->create(['supplier_id' => $this->supplier->id, 'designation' => 'Salesman']);
    $this->settlement = SalesSettlement::factory()->create([
        'employee_id' => $this->employee->id, 'supplier_id' => $this->supplier->id, 'status' => 'posted', 'settlement_date' => '2026-11-10',
    ]);

    $this->cheque = fn (float $amount, ?SalesSettlement $settlement = null, array $extra = []) => SalesSettlementCheque::create($extra + [
        'sales_settlement_id' => ($settlement ?? $this->settlement)->id, 'cheque_number' => 'CH-'.fake()->unique()->numerify('####'),
        'amount' => $amount, 'bank_name' => 'HBL', 'status' => 'pending',
    ]);

    $this->bulkClear = fn (array $cheques, array $payload = []) => $this->actingAs($this->user)->post(route('reports.cheque-register.bulk-clear'), $payload + [
        'cheque_ids' => collect($cheques)->pluck('id')->all(), 'cleared_date' => '2026-11-15', 'cleared_via' => 'cash',
    ]);

    $this->changeStatus = fn (SalesSettlementCheque $cheque, array $payload) => $this->actingAs($this->user)
        ->post(route('reports.cheque-register.update-status', $cheque), $payload);
});

/**
 * Net (debit minus credit) of the posted lines on one account.
 */
function batchLedgerNet(string $code): float
{
    $lines = JournalEntryDetail::whereHas('account', fn ($query) => $query->where('account_code', $code))
        ->whereHas('journalEntry', fn ($query) => $query->where('status', 'posted'))->get();

    return round((float) $lines->sum('debit') - (float) $lines->sum('credit'), 2);
}

it('refuses a cash clearing that mixes companies', function () {
    $this->user->forceFill(['is_super_admin' => 'Yes'])->save();
    $other = Supplier::factory()->create(['supplier_name' => 'Nestle Pakistan']);
    $otherSettlement = SalesSettlement::factory()->create([
        'employee_id' => Employee::factory()->create(['supplier_id' => $other->id])->id, 'supplier_id' => $other->id, 'status' => 'posted', 'settlement_date' => '2026-11-10',
    ]);
    $first = ($this->cheque)(1000);
    $second = ($this->cheque)(1000, $otherSettlement);

    ($this->bulkClear)([$first, $second], ['denom' => ['1000' => 2]])
        ->assertSessionHas('error', 'Cash clearing must be one company at a time. Selected: Kausar Oil, Nestle Pakistan.');

    expect($first->fresh()->status)->toBe('pending')->and(ChequeClearing::count())->toBe(0)->and(JournalEntry::count())->toBe(0);
});

it('stores the counted notes once on the clearing batch', function () {
    $first = ($this->cheque)(50100);
    $second = ($this->cheque)(3369);

    ($this->bulkClear)([$first, $second], ['denom' => ['5000' => 10, '1000' => 3, '100' => 4, '50' => 1, '10' => 1, 'coins' => 9]])
        ->assertSessionHasNoErrors()->assertSessionMissing('error');

    $batch = ChequeClearing::first();

    expect((float) $batch->total_amount)->toBe(53469.0)
        ->and($batch->supplier_id)->toBe($this->supplier->id)
        ->and($batch->denom_5000)->toBe(10)
        ->and($first->fresh()->cheque_clearing_id)->toBe($batch->id)
        ->and($second->fresh()->cheque_clearing_id)->toBe($batch->id)
        ->and($first->fresh()->cleared_denom_5000)->toBeNull()
        ->and($second->fresh()->cleared_denom_coins)->toBeNull()
        ->and(batchLedgerNet('1121'))->toBe(53469.0);
});

it('refuses a cleared date before the settlement date', function () {
    $cheque = ($this->cheque)(1000);

    ($this->bulkClear)([$cheque], ['cleared_date' => '2026-11-09', 'denom' => ['1000' => 1]])
        ->assertSessionHas('error', 'Cleared date must be between 10-Nov-2026 and today.');

    expect($cheque->fresh()->status)->toBe('pending');
});

it('refuses a future cleared date', function () {
    $cheque = ($this->cheque)(1000);

    ($this->bulkClear)([$cheque], ['cleared_date' => '2026-11-16', 'denom' => ['1000' => 1]])
        ->assertSessionHas('error', 'Cleared date must be between 10-Nov-2026 and today.');

    expect($cheque->fresh()->status)->toBe('pending');
});

it('refuses un-clearing on a later day', function () {
    $cheque = ($this->cheque)(1000);
    ($this->bulkClear)([$cheque], ['denom' => ['1000' => 1]])->assertSessionMissing('error');

    $this->travelTo('2026-11-16 09:00:00');

    ($this->changeStatus)($cheque, ['status' => 'pending'])
        ->assertSessionHas('error', 'This cheque was cleared on 15-Nov-2026; it can only be changed on that day.');

    expect($cheque->fresh()->status)->toBe('cleared')->and(ChequeClearing::count())->toBe(1);
});

it('reopens the whole batch when one cheque is un-cleared the same day', function () {
    $first = ($this->cheque)(1000);
    $second = ($this->cheque)(500);
    ($this->bulkClear)([$first, $second], ['denom' => ['1000' => 1, '500' => 1]])->assertSessionMissing('error');

    ($this->changeStatus)($first, ['status' => 'pending'])->assertSessionMissing('error');

    foreach ([$first->fresh(), $second->fresh()] as $cheque) {
        expect($cheque->status)->toBe('pending')
            ->and($cheque->cheque_clearing_id)->toBeNull()
            ->and($cheque->clearing_journal_entry_id)->toBeNull()
            ->and($cheque->cleared_date)->toBeNull();
    }

    expect(ChequeClearing::count())->toBe(0)
        ->and(batchLedgerNet('1121'))->toBe(0.0)
        ->and(batchLedgerNet('1122'))->toBe(0.0);
});

it('writes history for every cheque of a reopened batch, naming the batch and the reversal', function () {
    $first = ($this->cheque)(1000);
    $second = ($this->cheque)(500);
    ($this->bulkClear)([$first, $second], ['denom' => ['1000' => 1, '500' => 1]])->assertSessionMissing('error');
    $batchId = ChequeClearing::value('id');
    $clearingJournals = [$first->id => $first->fresh()->clearing_journal_entry_id, $second->id => $second->fresh()->clearing_journal_entry_id];

    ($this->changeStatus)($second, ['status' => 'pending'])->assertSessionMissing('error');

    foreach ([$first, $second] as $cheque) {
        $reopen = $cheque->histories()->first();
        $reversal = JournalEntry::find($reopen->journal_entry_id);

        expect($cheque->histories()->count())->toBe(2)
            ->and($reopen->from_status)->toBe('cleared')
            ->and($reopen->to_status)->toBe('pending')
            ->and($reopen->cheque_clearing_id)->toBe($batchId)
            ->and($reopen->reversed_journal_entry_id)->toBe($clearingJournals[$cheque->id])
            ->and($reversal->reverses_entry_id)->toBe($clearingJournals[$cheque->id])
            ->and($reopen->notes)->toContain('batch #'.$batchId);
    }
});

it('posts Dr Debtors Cr Cheques in Hand when a pending cheque bounces, with the customer ledger debited', function () {
    $customer = Customer::factory()->create();
    $account = CustomerEmployeeAccount::create([
        'account_number' => CustomerEmployeeAccount::generateAccountNumber(), 'customer_id' => $customer->id,
        'employee_id' => $this->employee->id, 'opened_date' => '2026-11-01', 'status' => 'active',
    ]);
    $cheque = ($this->cheque)(10000, null, ['customer_id' => $customer->id]);

    ($this->changeStatus)($cheque, ['status' => 'bounced', 'notes' => 'insufficient funds'])->assertSessionMissing('error');

    $cheque->refresh();
    $journal = JournalEntry::with('details.account')->find($cheque->bounce_journal_entry_id);
    $lines = $journal->details->mapWithKeys(fn ($line) => [$line->account->account_code => [(float) $line->debit, (float) $line->credit]]);
    $transaction = CustomerEmployeeAccountTransaction::where('customer_employee_account_id', $account->id)->sole();

    expect($cheque->status)->toBe('bounced')
        ->and($journal->entry_date->toDateString())->toBe('2026-11-15')
        ->and($lines->all())->toBe(['1112' => [10000.0, 0.0], '1122' => [0.0, 10000.0]])
        ->and($transaction->transaction_type)->toBe('cheque_bounce')
        ->and((float) $transaction->debit)->toBe(10000.0)
        ->and($transaction->journal_entry_id)->toBe($journal->id);
});

it('charges the bank charge to the customer against the deposit bank on a bounce', function () {
    $bank = BankAccount::factory()->create(['chart_of_account_id' => $this->ledger['1171']->id, 'is_active' => true]);
    $cheque = ($this->cheque)(10000, null, ['bank_account_id' => $bank->id]);

    ($this->changeStatus)($cheque, ['status' => 'bounced', 'bank_charge' => 150])->assertSessionMissing('error');

    expect(batchLedgerNet('1112'))->toBe(10150.0)
        ->and(batchLedgerNet('1122'))->toBe(-10000.0)
        ->and(batchLedgerNet('1171'))->toBe(-150.0)
        ->and((float) $cheque->histories()->first()->bank_charge)->toBe(150.0);
});

it('clears only once when submitted twice', function () {
    $cheque = ($this->cheque)(1000);

    ($this->bulkClear)([$cheque], ['denom' => ['1000' => 1]])->assertSessionMissing('error');
    ($this->bulkClear)([$cheque], ['denom' => ['1000' => 1]])->assertSessionHas('error');

    expect(ChequeClearing::count())->toBe(1)
        ->and(JournalEntry::count())->toBe(1)
        ->and($cheque->histories()->count())->toBe(1)
        ->and(batchLedgerNet('1121'))->toBe(1000.0);
});

it('keeps three history rows with their journals when a cheque is cleared, un-cleared and bounced the same day', function () {
    $cheque = ($this->cheque)(10000);

    ($this->changeStatus)($cheque, ['status' => 'cleared', 'cleared_date' => '2026-11-15', 'denom' => ['5000' => 2]])->assertSessionMissing('error');
    $clearingJournalId = $cheque->fresh()->clearing_journal_entry_id;
    $batchId = $cheque->fresh()->cheque_clearing_id;
    ($this->changeStatus)($cheque, ['status' => 'pending'])->assertSessionMissing('error');
    ($this->changeStatus)($cheque, ['status' => 'bounced'])->assertSessionMissing('error');

    [$bounce, $unclear, $clear] = $cheque->histories()->get()->all();
    $reversalId = JournalEntry::where('reverses_entry_id', $clearingJournalId)->value('id');

    expect($cheque->histories()->count())->toBe(3)
        ->and([$clear->from_status, $clear->to_status, $clear->cleared_via, $clear->cheque_clearing_id, $clear->journal_entry_id])->toBe(['pending', 'cleared', 'cash', $batchId, $clearingJournalId])
        ->and($clear->denominations)->toMatchArray(['denom_5000' => 2])
        ->and([$unclear->from_status, $unclear->to_status, $unclear->cheque_clearing_id, $unclear->journal_entry_id, $unclear->reversed_journal_entry_id])->toBe(['cleared', 'pending', $batchId, $reversalId, $clearingJournalId])
        ->and([$bounce->from_status, $bounce->to_status, $bounce->journal_entry_id])->toBe(['pending', 'bounced', $cheque->fresh()->bounce_journal_entry_id]);
});

it('leaves Cheques in Hand at zero for the cheque, debits Debtors and balances the ledger after a bounce from cleared', function () {
    $cheque = ($this->cheque)(10000);
    app(AccountingService::class)->createJournalEntry([
        'entry_date' => '2026-11-10', 'reference' => 'SS-TEST', 'description' => 'Settlement received the cheque',
        'lines' => [
            ['account_id' => $this->ledger['1122']->id, 'debit' => 10000, 'credit' => 0, 'cost_center_id' => 1],
            ['account_id' => $this->ledger['1112']->id, 'debit' => 0, 'credit' => 10000, 'cost_center_id' => 1],
        ],
        'auto_post' => true,
    ]);

    ($this->changeStatus)($cheque, ['status' => 'cleared', 'cleared_date' => '2026-11-15', 'denom' => ['5000' => 2]])->assertSessionMissing('error');
    ($this->changeStatus)($cheque, ['status' => 'bounced'])->assertSessionMissing('error');

    $bounceDebtors = JournalEntryDetail::where('journal_entry_id', $cheque->fresh()->bounce_journal_entry_id)
        ->where('chart_of_account_id', $this->ledger['1112']->id)->sum('debit');
    $posted = JournalEntryDetail::whereHas('journalEntry', fn ($query) => $query->where('status', 'posted'));

    expect($cheque->fresh()->status)->toBe('bounced')
        ->and($cheque->histories()->count())->toBe(3)
        ->and(batchLedgerNet('1122'))->toBe(0.0)
        ->and(batchLedgerNet('1121'))->toBe(0.0)
        ->and((float) $bounceDebtors)->toBe(10000.0)
        ->and(round((float) (clone $posted)->sum('debit'), 2))->toBe(round((float) (clone $posted)->sum('credit'), 2));
});

it('refuses a company user un-clearing a bank batch that holds another company\'s cheque', function () {
    $bank = BankAccount::factory()->create(['chart_of_account_id' => $this->ledger['1171']->id, 'is_active' => true]);
    $other = Supplier::factory()->create();
    $otherSettlement = SalesSettlement::factory()->create([
        'employee_id' => Employee::factory()->create(['supplier_id' => $other->id])->id, 'supplier_id' => $other->id, 'status' => 'posted', 'settlement_date' => '2026-11-10',
    ]);
    $own = ($this->cheque)(1000);
    $foreign = ($this->cheque)(500, $otherSettlement);
    $admin = User::factory()->create();
    $admin->forceFill(['is_super_admin' => 'Yes'])->save();
    $admin->givePermissionTo(['report-audit-cheque-register', 'report-audit-cheque-register-manage']);

    $this->actingAs($admin)->post(route('reports.cheque-register.bulk-clear'), [
        'cheque_ids' => [$own->id, $foreign->id], 'cleared_date' => '2026-11-15', 'cleared_via' => 'bank', 'cleared_bank_account_id' => $bank->id,
    ])->assertSessionMissing('error');

    expect(ChequeClearing::sole()->supplier_id)->toBeNull();

    ($this->changeStatus)($own, ['status' => 'pending'])->assertForbidden();

    expect($foreign->fresh()->status)->toBe('cleared')->and($own->fresh()->status)->toBe('cleared');
});
