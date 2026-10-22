<?php

use App\Models\BankReconciliation;
use App\Models\BankStatementEntry;
use App\Models\Currency;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\BankRegister\BankEntryApprovalService;
use App\Services\BankRegister\BankReconciliationService;
use App\Services\BankRegister\BankRegisterException;
use App\Services\BankRegister\BankRegisterLedger;

beforeEach(function () {
    $this->fixture = bankRegisterFixture();
    config()->set('bank.maker_checker.enabled', false);
    $this->user = bankUser(['bank-statement-entry-submit', 'bank-statement-entry-post'], 'Maker');
    $this->service = app(BankReconciliationService::class);
    $this->today = now()->toDateString();
});

function postedBankLine(array $fixture, User $user, float $amount, string $direction = 'credit'): BankStatementEntry
{
    $entry = BankStatementEntry::factory()->create([
        'bank_account_id' => $fixture['bank']->id, 'company_id' => $fixture['company']->id,
        'bank_entry_category_id' => $fixture['category']->id, 'posting_date' => now()->toDateString(),
        'credit' => $direction === 'credit' ? $amount : 0, 'debit' => $direction === 'debit' ? $amount : 0,
        'status' => BankStatementEntry::STATUS_CLASSIFIED,
    ]);

    return app(BankEntryApprovalService::class)->submit($entry, $user);
}

test('starting a reconciliation matches the posted lines and balances when the statement agrees with the books', function () {
    postedBankLine($this->fixture, $this->user, 1000);
    postedBankLine($this->fixture, $this->user, 300, 'debit');

    $rec = $this->service->start($this->fixture['bank'], now()->startOfMonth(), now()->endOfMonth(), 700, 0, null, $this->user);

    expect($rec->items)->toHaveCount(2)
        ->and((float) $rec->book_balance)->toBe(700.0)
        ->and((float) $rec->difference)->toBe(0.0)
        ->and((float) $rec->reconciled_total)->toBe(700.0);

    $completed = $this->service->complete($rec, $this->user);

    expect($completed->status)->toBe(BankReconciliation::STATUS_COMPLETED)
        ->and(BankStatementEntry::where('status', BankStatementEntry::STATUS_RECONCILED)->count())->toBe(2)
        ->and(BankStatementEntry::first()->bank_reconciliation_id)->toBe($rec->id);
});

test('the period cannot be locked while the difference is not zero', function () {
    postedBankLine($this->fixture, $this->user, 1000);
    $rec = $this->service->start($this->fixture['bank'], now()->startOfMonth(), now()->endOfMonth(), 1500, 0, null, $this->user);

    expect((float) $rec->difference)->toBe(500.0);
    expect(fn () => $this->service->complete($rec, $this->user))->toThrow(BankRegisterException::class, 'difference');
    expect($rec->fresh()->status)->toBe(BankReconciliation::STATUS_IN_PROGRESS);
});

test('a statement line that is not in the books blocks completion until it is posted', function () {
    postedBankLine($this->fixture, $this->user, 1000);
    BankStatementEntry::factory()->create([
        'bank_account_id' => $this->fixture['bank']->id, 'posting_date' => $this->today, 'credit' => 200, 'debit' => 0,
        'status' => BankStatementEntry::STATUS_UNCLASSIFIED,
    ]);

    $rec = $this->service->start($this->fixture['bank'], now()->startOfMonth(), now()->endOfMonth(), 1200, 0, null, $this->user);

    expect($this->service->refreshTotals($rec)['unmatched_entries'])->toHaveCount(1);
    expect(fn () => $this->service->complete($rec, $this->user))->toThrow(BankRegisterException::class, 'unmatched');
});

test('outstanding cheques and deposits in transit are carried to the reconciliation statement', function () {
    postedBankLine($this->fixture, $this->user, 1000);

    // a book entry made elsewhere (e.g. a settlement bank slip) that has not reached the bank yet
    $currencyId = Currency::value('id');
    $journal = JournalEntry::create(['currency_id' => $currencyId, 'entry_date' => $this->today, 'reference' => 'SLIP-1', 'description' => 'slip', 'status' => 'draft']);
    $journal->details()->create(['chart_of_account_id' => $this->fixture['bankLedger']->id, 'line_no' => 1, 'debit' => 400, 'credit' => 0]);
    $journal->details()->create(['chart_of_account_id' => app(BankRegisterLedger::class)->account('customer_receipts')->id, 'line_no' => 2, 'debit' => 0, 'credit' => 400]);
    $journal->update(['status' => 'posted']);

    $rec = $this->service->start($this->fixture['bank'], now()->startOfMonth(), now()->endOfMonth(), 1000, 0, null, $this->user);
    $totals = $this->service->refreshTotals($rec);

    expect($totals['deposits_in_transit'])->toBe(400.0)
        ->and($totals['book_balance'])->toBe(1400.0)
        ->and($totals['adjusted_statement'])->toBe(1400.0)
        ->and($totals['difference'])->toBe(0.0);

    $this->service->complete($rec, $this->user);
    expect($rec->fresh()->status)->toBe(BankReconciliation::STATUS_COMPLETED);
});

test('an unposted statement line can be matched with an existing book line of the same amount and direction', function () {
    $currencyId = Currency::value('id');
    $journal = JournalEntry::create(['currency_id' => $currencyId, 'entry_date' => $this->today, 'reference' => 'SLIP-2', 'description' => 'slip', 'status' => 'draft']);
    $bankLine = $journal->details()->create(['chart_of_account_id' => $this->fixture['bankLedger']->id, 'line_no' => 1, 'debit' => 250, 'credit' => 0]);
    $journal->details()->create(['chart_of_account_id' => app(BankRegisterLedger::class)->account('customer_receipts')->id, 'line_no' => 2, 'debit' => 0, 'credit' => 250]);
    $journal->update(['status' => 'posted']);

    $entry = BankStatementEntry::factory()->create([
        'bank_account_id' => $this->fixture['bank']->id, 'posting_date' => $this->today, 'credit' => 250, 'debit' => 0,
    ]);
    $wrong = BankStatementEntry::factory()->create([
        'bank_account_id' => $this->fixture['bank']->id, 'posting_date' => $this->today, 'credit' => 0, 'debit' => 250,
    ]);

    $rec = $this->service->start($this->fixture['bank'], now()->startOfMonth(), now()->endOfMonth(), 250, 0, null, $this->user);
    expect($this->service->suggestions($rec, $entry))->toHaveCount(1);

    expect(fn () => $this->service->match($rec, $wrong, $bankLine, $this->user))->toThrow(BankRegisterException::class, 'same amount and direction');

    $this->service->match($rec, $entry, $bankLine, $this->user);
    expect($entry->fresh()->status)->toBe(BankStatementEntry::STATUS_POSTED)
        ->and($entry->fresh()->journal_entry_id)->toBe($journal->id);

    $item = $rec->items()->where('bank_statement_entry_id', $entry->id)->first();
    $this->service->unmatch($rec, $item, $this->user);
    expect($entry->fresh()->status)->toBe(BankStatementEntry::STATUS_UNCLASSIFIED)->and($entry->fresh()->journal_entry_id)->toBeNull();
});

test('cancelling a completed reconciliation unlocks the period', function () {
    postedBankLine($this->fixture, $this->user, 1000);
    $rec = $this->service->start($this->fixture['bank'], now()->startOfMonth(), now()->endOfMonth(), 1000, 0, null, $this->user);
    $this->service->complete($rec, $this->user);

    $this->service->cancel($rec->fresh(), $this->user);

    expect($rec->fresh()->status)->toBe(BankReconciliation::STATUS_CANCELLED)
        ->and(BankStatementEntry::first()->status)->toBe(BankStatementEntry::STATUS_POSTED)
        ->and(BankStatementEntry::first()->bank_reconciliation_id)->toBeNull();

    // the period is free again
    expect($this->service->start($this->fixture['bank'], now()->startOfMonth(), now()->endOfMonth(), 1000, 0, null, $this->user)->items)->toHaveCount(1);
});

test('two reconciliations cannot overlap for the same account', function () {
    $this->service->start($this->fixture['bank'], now()->startOfMonth(), now()->endOfMonth(), 0, 0, null, $this->user);

    expect(fn () => $this->service->start($this->fixture['bank'], now()->startOfMonth(), now()->endOfMonth(), 0, 0, null, $this->user))
        ->toThrow(BankRegisterException::class, 'already covers');
});

test('a completed reconciliation can no longer be changed', function () {
    postedBankLine($this->fixture, $this->user, 1000);
    $rec = $this->service->start($this->fixture['bank'], now()->startOfMonth(), now()->endOfMonth(), 1000, 0, null, $this->user);
    $this->service->complete($rec, $this->user);

    expect(fn () => $this->service->autoMatch($rec->fresh(), $this->user))->toThrow(BankRegisterException::class, 'can no longer be changed');
});

test('the reconciliation screens render for a user with the right permissions', function () {
    postedBankLine($this->fixture, $this->user, 1000);
    $user = bankUser(['bank-reconciliation-list', 'bank-reconciliation-create', 'bank-reconciliation-edit', 'bank-reconciliation-complete', 'bank-reconciliation-cancel', 'bank-statement-entry-list']);
    $this->actingAs($user);

    $this->get(route('bank-reconciliations.create'))->assertOk()->assertSee('Start reconciliation');

    $this->post(route('bank-reconciliations.store'), [
        'bank_account_id' => $this->fixture['bank']->id, 'period_from' => now()->startOfMonth()->toDateString(),
        'period_to' => now()->endOfMonth()->toDateString(), 'opening_balance' => 0, 'statement_balance' => 1000,
    ])->assertRedirect();

    $rec = BankReconciliation::firstOrFail();
    $this->get(route('bank-reconciliations.show', $rec))->assertOk()->assertSee('Difference')->assertSee('Balanced');
    $this->get(route('bank-reconciliations.index'))->assertOk();

    $this->post(route('bank-reconciliations.complete', $rec))->assertSessionHas('success');
    expect($rec->fresh()->status)->toBe('completed');
});
