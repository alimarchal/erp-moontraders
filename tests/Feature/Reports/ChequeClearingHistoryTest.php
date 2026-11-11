<?php

use App\Models\BankAccount;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementCheque;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ChequeClearingPostingService;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->ledger = seedExpenseLedger();
    Permission::findOrCreate('report-audit-cheque-register');
    Permission::findOrCreate('report-audit-cheque-register-manage');

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['report-audit-cheque-register', 'report-audit-cheque-register-manage']);

    $supplier = Supplier::factory()->create();
    $this->user->update(['supplier_id' => $supplier->id]);
    $employee = Employee::factory()->create(['supplier_id' => $supplier->id, 'designation' => 'Salesman']);
    $settlement = SalesSettlement::factory()->create(['employee_id' => $employee->id, 'supplier_id' => $supplier->id, 'status' => 'posted']);
    $this->cheque = SalesSettlementCheque::create([
        'sales_settlement_id' => $settlement->id, 'cheque_number' => 'CH-1', 'amount' => 10000, 'bank_name' => 'HBL', 'status' => 'pending',
    ]);
});

it('clears a cheque with the cleared amount defaulting to the cheque amount and records history', function () {
    $this->actingAs($this->user)
        ->post(route('reports.cheque-register.update-status', $this->cheque), ['status' => 'cleared', 'cleared_date' => '2026-10-28', 'denom' => ['5000' => 2]])
        ->assertRedirect();

    $this->cheque->refresh();
    expect($this->cheque->status)->toBe('cleared')
        ->and((float) $this->cheque->cleared_amount)->toBe(10000.0)
        ->and($this->cheque->histories)->toHaveCount(1)
        ->and($this->cheque->histories->first()->from_status)->toBe('pending')
        ->and($this->cheque->histories->first()->changed_by)->toBe($this->user->id);
});

it('always clears the full cheque amount and requires a date when clearing', function () {
    $this->actingAs($this->user)
        ->post(route('reports.cheque-register.update-status', $this->cheque), ['status' => 'cleared', 'denom' => ['5000' => 2]])
        ->assertSessionHasErrors('cleared_date');

    $this->actingAs($this->user)
        ->post(route('reports.cheque-register.update-status', $this->cheque), ['status' => 'cleared', 'cleared_date' => '2026-10-28', 'cleared_amount' => 9500, 'notes' => 'bank charges', 'denom' => ['5000' => 2]])
        ->assertSessionHasNoErrors();

    expect((float) $this->cheque->fresh()->cleared_amount)->toBe(10000.0)
        ->and($this->cheque->histories()->first()->notes)->toBe('[Cash brought back] bank charges');
});

it('clears the cleared date and amount when a cheque goes back, but keeps the history', function () {
    $this->actingAs($this->user)->post(route('reports.cheque-register.update-status', $this->cheque), ['status' => 'cleared', 'cleared_date' => '2026-10-28', 'denom' => ['1000' => 10]]);
    $this->actingAs($this->user)->post(route('reports.cheque-register.update-status', $this->cheque), ['status' => 'bounced', 'notes' => 'returned by bank']);

    $this->cheque->refresh();
    expect($this->cheque->cleared_date)->toBeNull()
        ->and($this->cheque->cleared_amount)->toBeNull()
        ->and($this->cheque->histories)->toHaveCount(2);
});

it('returns the status history as json for users who can view the register', function () {
    $this->actingAs($this->user)->post(route('reports.cheque-register.update-status', $this->cheque), ['status' => 'cleared', 'cleared_date' => '2026-10-28', 'denom' => ['5000' => 2]]);

    $this->actingAs($this->user)->getJson(route('reports.cheque-register.history', $this->cheque))
        ->assertOk()
        ->assertJsonPath('cheque_number', 'CH-1')
        ->assertJsonPath('entries.0.to', 'Cleared');

    $this->actingAs(User::factory()->create())->getJson(route('reports.cheque-register.history', $this->cheque))->assertForbidden();
});

it('feeds the distributor report with the cleared amount on the cleared date', function () {
    Permission::findOrCreate('report-audit-cash-collection-distributor');
    Permission::findOrCreate('report-audit-cash-collection-distributor-all-companies');
    $this->user->givePermissionTo(['report-audit-cash-collection-distributor', 'report-audit-cash-collection-distributor-all-companies']);

    $this->actingAs($this->user)->post(route('reports.cheque-register.update-status', $this->cheque), ['status' => 'cleared', 'cleared_date' => '2026-10-28', 'denom' => ['5000' => 1, '1000' => 4, '500' => 2]]);

    $tables = $this->actingAs($this->user)
        ->get(route('reports.cash-collection-distributor.index', ['supplier_id' => 'all', 'date' => '2026-10-28']))
        ->viewData('tables');

    expect($tables['cash']['rows'][0][3])->toBe('10,000.00');
});

it('counts the notes brought back for a cleared cheque in the cash denominations of the distributor report', function () {
    Permission::findOrCreate('report-audit-cash-collection-distributor');
    Permission::findOrCreate('report-audit-cash-collection-distributor-all-companies');
    $this->user->givePermissionTo(['report-audit-cash-collection-distributor', 'report-audit-cash-collection-distributor-all-companies']);

    $this->actingAs($this->user)->post(route('reports.cheque-register.update-status', $this->cheque), [
        'status' => 'cleared', 'cleared_date' => '2026-10-28', 'denom' => ['5000' => 1, '1000' => 4, '500' => 2],
    ])->assertSessionHasNoErrors();

    $cheque = $this->cheque->fresh();
    expect($cheque->cleared_denom_5000)->toBe(1)->and($cheque->cleared_denom_1000)->toBe(4)->and($cheque->cleared_denom_100)->toBeNull();

    $rows = collect($this->actingAs($this->user)
        ->get(route('reports.cash-collection-distributor.index', ['supplier_id' => 'all', 'date' => '2026-10-28']))
        ->viewData('tables')['denominations']['rows']);

    expect($rows->firstWhere(1, '5,000')[2])->toBe(1)->and($rows->firstWhere(1, '1,000')[2])->toBe(4)
        ->and($rows->firstWhere(1, 'Cheque clearing (notes not counted)')[3])->toBe('-');
});

it('refuses notes that do not add up to the cleared amount, and drops them when the cheque goes back', function () {
    $this->actingAs($this->user)->post(route('reports.cheque-register.update-status', $this->cheque), [
        'status' => 'cleared', 'cleared_date' => '2026-10-28', 'denom' => ['5000' => 1],
    ])->assertSessionHas('error');

    $this->actingAs($this->user)->post(route('reports.cheque-register.update-status', $this->cheque), ['status' => 'cleared', 'cleared_date' => '2026-10-28'])->assertSessionHas('error');
    expect($this->cheque->fresh()->status)->toBe('pending');

    $this->actingAs($this->user)->post(route('reports.cheque-register.update-status', $this->cheque), ['status' => 'cleared', 'cleared_date' => '2026-10-28', 'denom' => ['5000' => 2]])->assertSessionHasNoErrors();
    $this->actingAs($this->user)->post(route('reports.cheque-register.update-status', $this->cheque), ['status' => 'bounced']);

    expect($this->cheque->fresh()->cleared_denom_5000)->toBeNull();
});

it('lets a cleared cheque be corrected on the day it was cleared but not on a later day', function () {
    $clear = fn (array $denom) => $this->actingAs($this->user)->post(route('reports.cheque-register.update-status', $this->cheque), ['status' => 'cleared', 'cleared_date' => now()->toDateString(), 'denom' => $denom]);

    $clear(['5000' => 2])->assertSessionHasNoErrors();
    $clear(['1000' => 10])->assertSessionHasNoErrors();
    expect($this->cheque->fresh()->cleared_denom_5000)->toBeNull()->and($this->cheque->fresh()->cleared_denom_1000)->toBe(10);

    $this->cheque->update(['cleared_date' => now()->subDay()->toDateString()]);
    $clear(['5000' => 2])->assertSessionHas('error');
    expect($this->cheque->fresh()->cleared_denom_1000)->toBe(10);
});

/**
 * @return array{debit: float, credit: float}
 */
function chequeLedger(string $code): array
{
    $lines = JournalEntryDetail::whereHas('account', fn ($query) => $query->where('account_code', $code))
        ->whereHas('journalEntry', fn ($query) => $query->where('status', 'posted'))->get();

    return ['debit' => (float) $lines->sum('debit'), 'credit' => (float) $lines->sum('credit')];
}

it('books cash brought back for a cleared cheque out of Cheques in Hand into Cash', function () {
    $this->actingAs($this->user)->post(route('reports.cheque-register.update-status', $this->cheque), ['status' => 'cleared', 'cleared_date' => '2026-10-28', 'denom' => ['5000' => 2]])
        ->assertSessionHasNoErrors();

    $cheque = $this->cheque->fresh();
    $journal = JournalEntry::find($cheque->clearing_journal_entry_id);

    expect($cheque->cleared_via)->toBe('cash')
        ->and($journal->entry_date->toDateString())->toBe('2026-10-28')
        ->and(chequeLedger('1121'))->toBe(['debit' => 10000.0, 'credit' => 0.0])
        ->and(chequeLedger('1122'))->toBe(['debit' => 0.0, 'credit' => 10000.0]);
});

it('books a cheque the bank cleared into that bank, without notes and outside the cash report', function () {
    $bank = BankAccount::factory()->create(['chart_of_account_id' => $this->ledger['1171']->id, 'is_active' => true]);
    Permission::findOrCreate('report-audit-cash-collection-distributor');
    Permission::findOrCreate('report-audit-cash-collection-distributor-all-companies');
    $this->user->givePermissionTo(['report-audit-cash-collection-distributor', 'report-audit-cash-collection-distributor-all-companies']);

    $this->actingAs($this->user)->post(route('reports.cheque-register.update-status', $this->cheque), ['status' => 'cleared', 'cleared_date' => '2026-10-28', 'cleared_via' => 'bank'])
        ->assertSessionHasErrors('cleared_bank_account_id');

    $this->actingAs($this->user)->post(route('reports.cheque-register.update-status', $this->cheque), [
        'status' => 'cleared', 'cleared_date' => '2026-10-28', 'cleared_via' => 'bank', 'cleared_bank_account_id' => $bank->id, 'denom' => ['5000' => 2],
    ])->assertSessionHasNoErrors();

    expect($this->cheque->fresh()->cleared_denom_5000)->toBeNull()
        ->and(chequeLedger('1171'))->toBe(['debit' => 10000.0, 'credit' => 0.0])
        ->and(chequeLedger('1121'))->toBe(['debit' => 0.0, 'credit' => 0.0]);

    $tables = $this->actingAs($this->user)->get(route('reports.cash-collection-distributor.index', ['supplier_id' => 'all', 'date' => '2026-10-28']))->viewData('tables');
    expect(collect($tables['summary']['rows'])->firstWhere(0, 'Cheque clearing cash')[1])->toBe('0.00');
});

it('takes the booking back when a cleared cheque bounces, so Cheques in Hand holds it again', function () {
    $this->actingAs($this->user)->post(route('reports.cheque-register.update-status', $this->cheque), ['status' => 'cleared', 'cleared_date' => now()->toDateString(), 'denom' => ['5000' => 2]]);
    $this->actingAs($this->user)->post(route('reports.cheque-register.update-status', $this->cheque), ['status' => 'bounced'])->assertSessionHasNoErrors();

    $cash = chequeLedger('1121');
    $inHand = chequeLedger('1122');

    expect($this->cheque->fresh()->clearing_journal_entry_id)->toBeNull()
        ->and($cash['debit'] - $cash['credit'])->toBe(0.0)
        ->and($inHand['debit'] - $inHand['credit'])->toBe(0.0);
});

it('refuses to clear a cheque of a settlement that is not posted, and changes nothing', function () {
    $this->cheque->salesSettlement->update(['status' => 'draft']);

    $this->actingAs($this->user)->post(route('reports.cheque-register.update-status', $this->cheque), ['status' => 'cleared', 'cleared_date' => '2026-10-28', 'denom' => ['5000' => 2]])
        ->assertSessionHas('error');

    expect($this->cheque->fresh()->status)->toBe('pending')->and(JournalEntry::count())->toBe(0);
});

it('clears selected pending cheques together and shares the counted notes over them', function () {
    $second = SalesSettlementCheque::create([
        'sales_settlement_id' => $this->cheque->sales_settlement_id, 'cheque_number' => 'CH-2', 'amount' => 2500, 'bank_name' => 'MCB', 'status' => 'pending',
    ]);

    $this->actingAs($this->user)->post(route('reports.cheque-register.bulk-clear'), [
        'cheque_ids' => [$this->cheque->id, $second->id], 'cleared_date' => '2026-10-28', 'cleared_via' => 'cash', 'denom' => ['5000' => 1, '1000' => 7],
    ])->assertSessionHas('error');

    $this->actingAs($this->user)->post(route('reports.cheque-register.bulk-clear'), [
        'cheque_ids' => [$this->cheque->id, $second->id], 'cleared_date' => '2026-10-28', 'cleared_via' => 'cash', 'denom' => ['5000' => 2, '1000' => 2, '500' => 1],
    ])->assertSessionHasNoErrors();

    $first = $this->cheque->fresh();
    $second->refresh();

    expect($first->status)->toBe('cleared')->and($second->status)->toBe('cleared')
        ->and($first->cleared_denom_5000)->toBe(2)
        ->and($second->cleared_denom_1000)->toBe(2)->and($second->cleared_denom_500)->toBe(1)
        ->and(chequeLedger('1121'))->toBe(['debit' => 12500.0, 'credit' => 0.0]);

    $this->actingAs($this->user)->post(route('reports.cheque-register.bulk-clear'), [
        'cheque_ids' => [$this->cheque->id], 'cleared_date' => '2026-10-28', 'cleared_via' => 'cash', 'denom' => ['5000' => 2],
    ])->assertSessionHas('error');
});

it('books cheques cleared before clearing was booked, on the day they were marked cleared', function () {
    $this->cheque->forceFill(['status' => 'cleared', 'cleared_date' => null, 'status_updated_at' => '2026-10-20 15:00:00'])->saveQuietly();

    $result = app(ChequeClearingPostingService::class)->postMissing();
    $cheque = $this->cheque->fresh();

    expect($result)->toBe(['cheques' => 1, 'amount' => 10000.0])
        ->and($cheque->cleared_via)->toBe('cash')
        ->and($cheque->cleared_date)->toBeNull()
        ->and(JournalEntry::find($cheque->clearing_journal_entry_id)->entry_date->toDateString())->toBe('2026-10-20')
        ->and(app(ChequeClearingPostingService::class)->postMissing()['cheques'])->toBe(0);
});
