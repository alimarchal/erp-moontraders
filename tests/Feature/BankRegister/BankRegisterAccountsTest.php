<?php

use App\Models\AccountType;
use App\Models\BankEntryCategory;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationItem;
use App\Models\BankStatementEntry;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\JournalEntry;
use App\Services\BankRegister\BankEntryApprovalService;
use App\Services\BankRegister\BankRegisterAccounts;
use App\Services\BankRegister\BankReportService;
use App\Services\BankRegister\MemoLedgerRemoval;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->fixture = bankRegisterFixture();
    config()->set('bank.maker_checker.enabled', false);
    $this->accounts = app(BankRegisterAccounts::class);
    $this->maker = bankUser(['bank-statement-entry-list', 'bank-statement-entry-submit', 'bank-statement-entry-post'], 'Maker');
});


function postRegisterEntry(array $fixture, $maker, array $overrides = []): BankStatementEntry
{
    $entry = BankStatementEntry::factory()->create($overrides + [
        'bank_account_id' => $fixture['bank']->id, 'company_id' => $fixture['company']->id, 'bank_entry_category_id' => $fixture['category']->id,
        'posting_date' => now()->toDateString(), 'credit' => 5000, 'debit' => 0, 'status' => 'classified',
    ]);

    return app(BankEntryApprovalService::class)->submit($entry, $maker);
}

/**
 * A balanced, posted journal on the given accounts.
 */
function memoJournal(string $reference, int $debitAccountId, int $creditAccountId, float $amount, ?int $reversesEntryId = null): JournalEntry
{
    $journal = new JournalEntry([
        'currency_id' => Currency::value('id'),
        'entry_date' => now()->toDateString(),
        'reference' => $reference,
        'description' => $reference,
        'status' => 'draft',
    ]);
    $journal->reverses_entry_id = $reversesEntryId;
    $journal->save();
    $journal->details()->create(['chart_of_account_id' => $debitAccountId, 'line_no' => 1, 'debit' => $amount, 'credit' => 0]);
    $journal->details()->create(['chart_of_account_id' => $creditAccountId, 'line_no' => 2, 'debit' => 0, 'credit' => $amount]);
    $journal->update(['status' => 'posted']);

    return $journal;
}

/**
 * The former memo ledger as it was on the live database: its own account type, root 6000, a bank and a clearing account.
 *
 * @return array{type: AccountType, root: ChartOfAccount, bank: ChartOfAccount, clearing: ChartOfAccount}
 */
function memoLedger(array $fixture): array
{
    // the memo group was allowed by a CHECK that the cleanup migration narrowed again
    DB::statement('ALTER TABLE account_types DROP CONSTRAINT IF EXISTS chk_report_group');

    $type = AccountType::create(['type_name' => 'Bank Register', 'report_group' => 'BankRegister']);
    $make = fn (string $code, string $name, ?int $parent, bool $group) => ChartOfAccount::create([
        'parent_id' => $parent, 'account_type_id' => $type->id, 'currency_id' => $fixture['liveLedger']->currency_id,
        'account_code' => $code, 'account_name' => $name, 'normal_balance' => 'debit', 'is_group' => $group, 'is_active' => true,
    ]);
    $root = $make('6000', 'Bank Register (memo accounts)', null, true);

    return ['type' => $type, 'root' => $root, 'bank' => $make('6101', 'BR - HBL', $root->id, false), 'clearing' => $make('6201', 'BR - Customer Receipts Clearing', $root->id, false)];
}

test('the accounts the register needs are created once in the main chart, under their group when it exists', function () {
    $type = AccountType::firstWhere('type_name', 'Assets');
    $taxGroup = ChartOfAccount::create(['account_type_id' => $type->id, 'currency_id' => $this->fixture['liveLedger']->currency_id, 'account_code' => '1160', 'account_name' => 'Tax Assets', 'normal_balance' => 'debit', 'is_group' => true, 'is_active' => true]);
    ChartOfAccount::create(['account_type_id' => $type->id, 'currency_id' => $this->fixture['liveLedger']->currency_id, 'account_code' => '1165', 'account_name' => 'Some live tax account', 'normal_balance' => 'debit', 'is_active' => true]);

    $this->accounts->ensureAll();
    $count = ChartOfAccount::count();
    $this->accounts->ensureAll();

    $tax = $this->accounts->account('bank_tax');

    expect(ChartOfAccount::count())->toBe($count)
        ->and($tax->parent_id)->toBe($taxGroup->id)
        ->and($tax->account_code)->toBe('1166') // 1165 is taken by a live account and stays as it is
        ->and(ChartOfAccount::pluck('account_code')->duplicates())->toBeEmpty()
        ->and(ChartOfAccount::where('account_code', 'like', '6%')->exists())->toBeFalse();
});

test('the bank side of a register journal is the bank account\'s own live ledger', function () {
    $posted = postRegisterEntry($this->fixture, $this->maker);

    expect($posted->journalEntryDetail->chart_of_account_id)->toBe($this->fixture['liveLedger']->id)
        ->and($this->accounts->bankLedger($this->fixture['bank'])->id)->toBe($this->fixture['liveLedger']->id);
});

test('a category with its own account keeps using it and unknown categories go to the bank suspense account', function () {
    $type = AccountType::firstWhere('type_name', 'Assets');
    $own = ChartOfAccount::create(['account_type_id' => $type->id, 'currency_id' => $this->fixture['liveLedger']->currency_id, 'account_code' => '5241', 'account_name' => 'Office Rent', 'normal_balance' => 'debit', 'is_active' => true]);
    $rent = BankEntryCategory::factory()->create(['name' => 'Rent', 'slug' => 'rent', 'type' => 'payment', 'default_chart_of_account_id' => $own->id]);

    $posted = postRegisterEntry($this->fixture, $this->maker, ['bank_entry_category_id' => $rent->id, 'company_id' => null, 'credit' => 0, 'debit' => 900]);
    expect($posted->journalEntry->details->pluck('chart_of_account_id'))->toContain($own->id);

    $unknown = BankEntryCategory::factory()->create(['name' => 'Misc', 'slug' => 'misc', 'type' => 'receipt']);
    $suspense = postRegisterEntry($this->fixture, $this->maker, ['bank_entry_category_id' => $unknown->id, 'company_id' => null, 'credit' => 450, 'debit' => 0]);
    expect($suspense->journalEntry->details->pluck('chart_of_account_id'))->toContain($this->accounts->account('suspense')->id)
        ->and($this->accounts->account('suspense')->account_name)->toBe('Bank Suspense');
});

test('the former memo ledger is removed for good and its posted lines go back to the register', function () {
    $memo = memoLedger($this->fixture);
    $journal = memoJournal('BR-2026-27-000001', $memo['bank']->id, $memo['clearing']->id, 2500);
    $reversal = memoJournal('REV-BR-2026-27-000001', $memo['clearing']->id, $memo['bank']->id, 2500, $journal->id);
    $live = memoJournal('SETTLE-1', $this->fixture['liveLedger']->id, $this->fixture['liveReceivable']->id, 9000);

    $entry = BankStatementEntry::factory()->create([
        'bank_account_id' => $this->fixture['bank']->id, 'bank_entry_category_id' => $this->fixture['category']->id, 'credit' => 2500, 'debit' => 0,
        'status' => BankStatementEntry::STATUS_POSTED, 'receipt_no' => 'BR-2026-27-000001', 'journal_entry_id' => $journal->id,
        'journal_entry_detail_id' => $journal->details()->where('line_no', 1)->value('id'),
    ]);
    $reconciliation = BankReconciliation::create([
        'bank_account_id' => $this->fixture['bank']->id, 'period_from' => now()->startOfMonth(), 'period_to' => now()->endOfMonth(),
        'statement_date' => now()->endOfMonth(), 'statement_balance' => 0, 'opening_balance' => 0, 'book_balance' => 0, 'difference' => 0,
        'status' => BankReconciliation::STATUS_IN_PROGRESS, 'created_by' => $this->maker->id,
    ]);
    BankReconciliationItem::create(['bank_reconciliation_id' => $reconciliation->id, 'bank_statement_entry_id' => $entry->id, 'journal_entry_detail_id' => $entry->journal_entry_detail_id, 'match_type' => 'auto', 'matched_by' => $this->maker->id, 'matched_at' => now()]);

    $result = app(MemoLedgerRemoval::class)->run();

    expect($result)->toMatchArray(['accounts' => 3, 'journals' => 2, 'lines' => 4, 'entries_unposted' => 1, 'reconciliation_items' => 1])
        ->and(ChartOfAccount::whereIn('id', [$memo['root']->id, $memo['bank']->id, $memo['clearing']->id])->exists())->toBeFalse()
        ->and(AccountType::where('report_group', 'BankRegister')->exists())->toBeFalse()
        ->and(JournalEntry::whereIn('id', [$journal->id, $reversal->id])->exists())->toBeFalse()
        ->and(JournalEntry::find($live->id)->details)->toHaveCount(2)
        ->and($entry->refresh()->status)->toBe(BankStatementEntry::STATUS_CLASSIFIED)
        ->and($entry->receipt_no)->toBe('BR-2026-27-000001')
        ->and($entry->journal_entry_id)->toBeNull();

    // the journal guards are back: a posted journal still cannot be deleted
    expect(fn () => JournalEntry::find($live->id)->forceDelete())->toThrow(Exception::class);
});

test('nothing is removed when a memo journal touches a live account', function () {
    $memo = memoLedger($this->fixture);
    $mixed = memoJournal('BR-2026-27-000009', $memo['bank']->id, $this->fixture['liveReceivable']->id, 100);

    expect(fn () => app(MemoLedgerRemoval::class)->run())->toThrow(RuntimeException::class, 'outside the register')
        ->and(JournalEntry::find($mixed->id))->not->toBeNull()
        ->and(ChartOfAccount::find($memo['bank']->id))->not->toBeNull();
});

test('the live-gap report lists statement lines the live ledger has no entry for', function () {
    $matched = BankStatementEntry::factory()->create([
        'bank_account_id' => $this->fixture['bank']->id, 'posting_date' => now()->toDateString(), 'credit' => 7000, 'debit' => 0, 'party_name_in_statement' => 'IN LIVE',
    ]);
    BankStatementEntry::factory()->create([
        'bank_account_id' => $this->fixture['bank']->id, 'posting_date' => now()->toDateString(), 'credit' => 0, 'debit' => 3300, 'party_name_in_statement' => 'ONLY IN BANK',
    ]);

    // a live journal (as a settlement would book it) for the first line, on the LIVE ledger
    $journal = JournalEntry::create(['currency_id' => Currency::value('id'), 'entry_date' => now()->subDay()->toDateString(), 'reference' => 'SETTLEMENT-1', 'description' => 'live', 'status' => 'draft']);
    $journal->details()->create(['chart_of_account_id' => $this->fixture['liveLedger']->id, 'line_no' => 1, 'debit' => 7000, 'credit' => 0]);
    $journal->details()->create(['chart_of_account_id' => $this->fixture['liveReceivable']->id, 'line_no' => 2, 'debit' => 0, 'credit' => 7000]);
    $journal->update(['status' => 'posted']);

    $report = app(BankReportService::class)->build('live-gap', []);

    expect($report['rows'])->toHaveCount(1)->and($report['rows'][0][2])->toBe('ONLY IN BANK')->and($report['rows'][0][6])->toBe(3300.0)
        ->and($report['notes'][0])->toContain('1 statement line(s) were found');
});
