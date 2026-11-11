<?php

use App\Models\BankEntryAuditLog;
use App\Models\BankEntryCategory;
use App\Models\BankStatementEntry;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Services\BankRegister\BankEntryApprovalService;
use App\Services\BankRegister\BankEntryPostingService;
use App\Services\BankRegister\BankRegisterAccounts;
use App\Services\BankRegister\BankRegisterException;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->fixture = bankRegisterFixture();
    config()->set('bank.maker_checker', [
        'enabled' => true, 'level1_threshold' => 1000000.0, 'level2_threshold' => 10000000.0,
        'allow_self_approval' => false, 'applies_to' => 'both', 'reversal_requires_approval' => true,
        'bulk_approval_max_entries' => 100,
    ]);

    $this->maker = bankUser(['bank-statement-entry-list', 'bank-statement-entry-submit', 'bank-statement-entry-post'], 'Maker');
    $this->checker = bankUser(['bank-statement-entry-list', 'bank-statement-entry-approve', 'bank-statement-entry-reject'], 'Checker');
    $this->senior = bankUser(['bank-statement-entry-list', 'bank-statement-entry-approve', 'bank-statement-entry-approve-high-value'], 'Senior');
    $this->approvals = app(BankEntryApprovalService::class);
});

/**
 * Money another module booked on the bank (a settlement slip, a supplier payment): a posted, balanced journal.
 */
function bookedElsewhere(array $fixture, float $debit, string $date, string $reference = 'SETTLE-1'): JournalEntryDetail
{
    $journal = JournalEntry::create(['currency_id' => $fixture['liveLedger']->currency_id, 'entry_date' => $date, 'reference' => $reference, 'description' => 'Bank Deposit (Slip)', 'status' => 'draft']);
    $bankLine = $journal->details()->create(['chart_of_account_id' => $fixture['liveLedger']->id, 'line_no' => 1, 'debit' => $debit, 'credit' => 0, 'description' => 'Bank Deposit (Slip)']);
    $journal->details()->create(['chart_of_account_id' => $fixture['liveReceivable']->id, 'line_no' => 2, 'debit' => 0, 'credit' => $debit]);
    $journal->update(['status' => 'posted']);

    return $bankLine;
}

function classifiedEntry(array $fixture, float $amount, string $direction = 'credit'): BankStatementEntry
{
    return BankStatementEntry::factory()->create([
        'bank_account_id' => $fixture['bank']->id,
        'company_id' => $fixture['company']->id,
        'bank_entry_category_id' => $fixture['category']->id,
        'posting_date' => now()->toDateString(),
        'credit' => $direction === 'credit' ? $amount : 0,
        'debit' => $direction === 'debit' ? $amount : 0,
        'status' => BankStatementEntry::STATUS_CLASSIFIED,
    ]);
}

test('an entry below the first limit is posted by the maker straight away with a balanced journal', function () {
    $entry = classifiedEntry($this->fixture, 250000);

    $posted = $this->approvals->submit($entry, $this->maker);

    expect($posted->status)->toBe(BankStatementEntry::STATUS_POSTED)
        ->and($posted->receipt_no)->toStartWith('BR-')->toMatch('/^BR-\d{4}-\d{2}-000001$/')
        ->and($posted->journalEntry->status)->toBe('posted')
        ->and($posted->journalEntry->reference)->toBe($posted->receipt_no);

    $lines = $posted->journalEntry->details;
    expect($lines)->toHaveCount(2)
        ->and((float) $lines->sum('debit'))->toBe((float) $lines->sum('credit'))
        ->and($lines->firstWhere('chart_of_account_id', $this->fixture['bankLedger']->id)->debit)->toBe('250000.00')
        ->and($lines->firstWhere('chart_of_account_id', app(BankRegisterAccounts::class)->account('debtors')->id)->credit)->toBe('250000.00')
        ->and($posted->journalEntryDetail->chart_of_account_id)->toBe($this->fixture['bankLedger']->id);
});

test('a payment debits the company payable and credits the bank, with a BP receipt number', function () {
    $entry = classifiedEntry($this->fixture, 40000, 'debit');

    $posted = $this->approvals->submit($entry, $this->maker);

    $lines = $posted->journalEntry->details;
    expect($posted->receipt_no)->toStartWith('BP-')
        ->and($lines->firstWhere('chart_of_account_id', $this->fixture['bankLedger']->id)->credit)->toBe('40000.00')
        ->and($lines->firstWhere('chart_of_account_id', app(BankRegisterAccounts::class)->account('debtors')->id)->debit)->toBe('40000.00');
});

test('receipt numbers run in sequence per kind', function () {
    $first = $this->approvals->submit(classifiedEntry($this->fixture, 100), $this->maker);
    $second = $this->approvals->submit(classifiedEntry($this->fixture, 200), $this->maker);

    expect(substr($first->receipt_no, -6))->toBe('000001')->and(substr($second->receipt_no, -6))->toBe('000002');
});

test('the category default account is used on the other side when it is set', function () {
    $special = ChartOfAccount::create([
        'account_type_id' => $this->fixture['bankLedger']->account_type_id, 'currency_id' => $this->fixture['bankLedger']->currency_id,
        'account_code' => '5999', 'account_name' => 'Bank Charges', 'normal_balance' => 'debit', 'is_active' => true,
    ]);
    $this->fixture['category']->update(['default_chart_of_account_id' => $special->id]);

    $posted = $this->approvals->submit(classifiedEntry($this->fixture, 700, 'debit'), $this->maker);

    expect($posted->journalEntry->details->firstWhere('chart_of_account_id', $special->id)->debit)->toBe('700.00');
});

test('a category without an account of its own and an entry without a company go to the suspense account with the details in the narration', function () {
    $category = BankEntryCategory::factory()->create(['name' => 'Rent', 'slug' => 'rent', 'type' => 'payment']);
    $entry = BankStatementEntry::factory()->create([
        'bank_account_id' => $this->fixture['bank']->id, 'bank_entry_category_id' => $category->id, 'posting_date' => now()->toDateString(),
        'credit' => 0, 'debit' => 9000, 'reference' => 'CHQ 777', 'party_name_in_statement' => 'LANDLORD', 'narration' => 'October rent', 'status' => 'classified',
    ]);

    $posted = $this->approvals->submit($entry, $this->maker);

    $suspense = app(BankRegisterAccounts::class)->account('suspense');
    $line = $posted->journalEntry->details->firstWhere('chart_of_account_id', $suspense->id);
    expect($line->debit)->toBe('9000.00')
        ->and($line->description)->toBe($posted->receipt_no.' | LANDLORD | CHQ 777 | October rent')
        ->and($posted->journalEntry->description)->toBe($line->description)
        ->and($posted->journalEntry->reference)->toBe($posted->receipt_no);
});

test('well known categories post to their live account', function () {
    $charges = BankStatementEntry::factory()->create([
        'bank_account_id' => $this->fixture['bank']->id, 'bank_entry_category_id' => BankEntryCategory::where('slug', 'bank-charges')->value('id'),
        'posting_date' => now()->toDateString(), 'credit' => 0, 'debit' => 1250, 'status' => 'classified',
    ]);

    $posted = $this->approvals->submit($charges, $this->maker);

    expect($posted->journalEntry->details->pluck('chart_of_account_id'))->toContain(app(BankRegisterAccounts::class)->account('bank_charges')->id);
});

test('a register journal posts to the bank account\'s own live ledger, so it is in the trial balance', function () {
    $this->approvals->submit(classifiedEntry($this->fixture, 250000), $this->maker);
    $this->approvals->submit(classifiedEntry($this->fixture, 80000, 'debit'), $this->maker);

    $bankLines = JournalEntryDetail::where('chart_of_account_id', $this->fixture['liveLedger']->id);

    expect((float) $bankLines->sum('debit'))->toBe(250000.0)
        ->and((float) JournalEntryDetail::where('chart_of_account_id', $this->fixture['liveLedger']->id)->sum('credit'))->toBe(80000.0)
        ->and(JournalEntryDetail::count())->toBe(4);
});

test('money another module already booked on the bank cannot be posted again, only linked', function () {
    $bankLine = bookedElsewhere($this->fixture, 300000, now()->subDay()->toDateString());
    $settlementJournal = $bankLine->journalEntry;
    $entry = classifiedEntry($this->fixture, 300000);

    expect(fn () => $this->approvals->submit($entry, $this->maker))->toThrow(BankRegisterException::class, 'already booked');

    $linked = app(BankEntryPostingService::class)->link($entry, $bankLine, $this->maker);

    expect($linked->status)->toBe(BankStatementEntry::STATUS_POSTED)
        ->and($linked->journal_entry_id)->toBe($settlementJournal->id)
        ->and($linked->receipt_no)->toBeNull()
        ->and($linked->bookingLabel())->toBe('Linked to book entry')
        ->and(JournalEntry::count())->toBe(1);

    // unlinking leaves the other module's journal alone
    $unlinked = app(BankEntryPostingService::class)->reverse($linked, $this->maker, 'Wrong deposit');
    expect($unlinked->status)->toBe(BankStatementEntry::STATUS_CLASSIFIED)
        ->and($unlinked->journal_entry_id)->toBeNull()
        ->and(JournalEntry::count())->toBe(1);
});

test('one journal line carrying several deposits can be linked until its amount is used up', function () {
    $line = bookedElsewhere($this->fixture, 1000, now()->toDateString());
    $posting = app(BankEntryPostingService::class);

    $posting->link(classifiedEntry($this->fixture, 600), $line, $this->maker);
    $posting->link(classifiedEntry($this->fixture, 400), $line, $this->maker);

    expect($posting->unlinkedAmount($line))->toBe(0.0)
        ->and(fn () => $posting->link(classifiedEntry($this->fixture, 1), $line, $this->maker))->toThrow(BankRegisterException::class, 'left that is not linked');
});

test('a look-alike that really is a different transaction is posted once the reason is given', function () {
    bookedElsewhere($this->fixture, 5000, now()->toDateString());
    $entry = classifiedEntry($this->fixture, 5000);
    $entry->update(['not_in_books_reason' => 'Two separate deposits of the same amount']);

    $posted = $this->approvals->submit($entry, $this->maker);

    expect($posted->status)->toBe(BankStatementEntry::STATUS_POSTED)->and($posted->bookingLabel())->toBe('Posted by register');
});

test('an entry at the first limit waits for a checker and the maker cannot approve it', function () {
    $entry = classifiedEntry($this->fixture, 1000000);

    $pending = $this->approvals->submit($entry, $this->maker);
    expect($pending->status)->toBe(BankStatementEntry::STATUS_PENDING_APPROVAL)
        ->and($pending->approval_level_required)->toBe(1)
        ->and($pending->journal_entry_id)->toBeNull();

    expect(fn () => $this->approvals->approve($pending, $this->maker))
        ->toThrow(BankRegisterException::class, 'different person');

    $posted = $this->approvals->approve($pending, $this->checker, 'Checked the slip');
    expect($posted->status)->toBe(BankStatementEntry::STATUS_POSTED)
        ->and($posted->approved_by)->toBe($this->checker->id)
        ->and($posted->receipt_no)->not->toBeNull()
        ->and($posted->approvals->pluck('action')->all())->toBe(['submitted', 'approved']);
});

test('the limits come from config so changing them changes the outcome', function () {
    config()->set('bank.maker_checker.level1_threshold', 5000000.0);

    $posted = $this->approvals->submit(classifiedEntry($this->fixture, 2000000), $this->maker);

    expect($posted->status)->toBe(BankStatementEntry::STATUS_POSTED);
});

test('a very large entry needs the checker and then a different senior approver', function () {
    $pending = $this->approvals->submit(classifiedEntry($this->fixture, 12000000), $this->maker);
    expect($pending->approval_level_required)->toBe(2);

    $afterChecker = $this->approvals->approve($pending, $this->checker);
    expect($afterChecker->status)->toBe(BankStatementEntry::STATUS_PENDING_APPROVAL);

    expect(fn () => $this->approvals->approve($afterChecker, $this->checker))
        ->toThrow(BankRegisterException::class, 'already approved');

    $other = bankUser(['bank-statement-entry-approve'], 'Plain checker');
    expect(fn () => $this->approvals->approve($afterChecker, $other))
        ->toThrow(BankRegisterException::class, 'senior approver');

    $posted = $this->approvals->approve($afterChecker, $this->senior);
    expect($posted->status)->toBe(BankStatementEntry::STATUS_POSTED)
        ->and($posted->approvals->where('action', 'approved'))->toHaveCount(2);
});

test('rejecting needs a reason, sends the entry back and a resubmit starts a new round', function () {
    $pending = $this->approvals->submit(classifiedEntry($this->fixture, 3000000), $this->maker);

    expect(fn () => $this->approvals->reject($pending, $this->checker, '  '))->toThrow(BankRegisterException::class);

    $rejected = $this->approvals->reject($pending, $this->checker, 'Wrong company');
    expect($rejected->status)->toBe(BankStatementEntry::STATUS_REJECTED)
        ->and($rejected->rejection_reason)->toBe('Wrong company');

    $again = $this->approvals->submit($rejected, $this->maker);
    expect($again->status)->toBe(BankStatementEntry::STATUS_PENDING_APPROVAL)
        ->and($again->approval_round)->toBe(2)
        ->and($again->rejection_reason)->toBeNull();

    // approvals given in the first round do not count in the second
    expect($this->approvals->approve($again, $this->checker)->status)->toBe(BankStatementEntry::STATUS_POSTED);
});

test('editing the party or category while waiting withdraws the approval request', function () {
    $pending = $this->approvals->submit(classifiedEntry($this->fixture, 3000000), $this->maker);

    $this->approvals->resetApproval($pending);

    expect($pending->fresh()->status)->toBe(BankStatementEntry::STATUS_CLASSIFIED)
        ->and(BankEntryAuditLog::where('action', 'approval_reset')->count())->toBe(1);
});

test('with maker-checker switched off even a huge entry is posted by the maker', function () {
    config()->set('bank.maker_checker.enabled', false);

    expect($this->approvals->submit(classifiedEntry($this->fixture, 50000000), $this->maker)->status)->toBe(BankStatementEntry::STATUS_POSTED);
});

test('approval can be limited to debits only', function () {
    config()->set('bank.maker_checker.applies_to', 'debit');

    expect($this->approvals->submit(classifiedEntry($this->fixture, 3000000, 'credit'), $this->maker)->status)->toBe(BankStatementEntry::STATUS_POSTED)
        ->and($this->approvals->submit(classifiedEntry($this->fixture, 3000000, 'debit'), $this->maker)->status)->toBe(BankStatementEntry::STATUS_PENDING_APPROVAL);
});

test('self approval is allowed only when the setting says so', function () {
    config()->set('bank.maker_checker.allow_self_approval', true);
    $selfChecker = bankUser(['bank-statement-entry-submit', 'bank-statement-entry-approve'], 'Both');

    $pending = $this->approvals->submit(classifiedEntry($this->fixture, 3000000), $selfChecker);

    expect($this->approvals->approve($pending, $selfChecker)->status)->toBe(BankStatementEntry::STATUS_POSTED);
});

test('the posting service refuses an entry that still needs approval', function () {
    $pending = $this->approvals->submit(classifiedEntry($this->fixture, 3000000), $this->maker);

    expect(fn () => app(BankEntryPostingService::class)->post($pending, $this->maker))
        ->toThrow(BankRegisterException::class, 'needs approval');
});

test('an unclassified entry cannot be submitted', function () {
    $entry = BankStatementEntry::factory()->create(['bank_account_id' => $this->fixture['bank']->id]);

    expect(fn () => $this->approvals->submit($entry, $this->maker))->toThrow(BankRegisterException::class);
});

test('bulk approval approves what it can and reports the rest, within the configured limit', function () {
    $a = $this->approvals->submit(classifiedEntry($this->fixture, 3000000), $this->maker);
    $b = $this->approvals->submit(classifiedEntry($this->fixture, 4000000), $this->checker); // checker's own: cannot self-approve

    $result = $this->approvals->bulkApprove([$a->id, $b->id], $this->checker);

    expect($result['approved'])->toBe(1)->and($result['failed'])->toHaveKey($b->id);

    config()->set('bank.maker_checker.bulk_approval_max_entries', 1);
    expect(fn () => $this->approvals->bulkApprove([1, 2], $this->checker))->toThrow(BankRegisterException::class, 'at most 1');
});

test('a posted entry is reversed with a reversing journal and can be submitted again with the same receipt', function () {
    $posted = $this->approvals->submit(classifiedEntry($this->fixture, 800), $this->maker);
    $receipt = $posted->receipt_no;
    $journalId = $posted->journal_entry_id;

    $reversed = app(BankEntryPostingService::class)->reverse($posted, $this->checker, 'Posted to the wrong company');

    expect($reversed->status)->toBe(BankStatementEntry::STATUS_REVERSED)
        ->and($reversed->journal_entry_id)->toBeNull()
        ->and(JournalEntry::where('reverses_entry_id', $journalId)->exists())->toBeTrue();

    $again = $this->approvals->submit($reversed, $this->maker);
    expect($again->status)->toBe(BankStatementEntry::STATUS_POSTED)->and($again->receipt_no)->toBe($receipt);
});

test('a reconciled entry cannot be reversed', function () {
    $entry = classifiedEntry($this->fixture, 800);
    $entry->update(['status' => BankStatementEntry::STATUS_RECONCILED]);

    expect(fn () => app(BankEntryPostingService::class)->reverse($entry, $this->checker, 'x'))->toThrow(BankRegisterException::class, 'reconciled');
});

test('an entry that needed approval cannot be reversed by the person who submitted it, only by someone else', function () {
    $pending = $this->approvals->submit(classifiedEntry($this->fixture, 3000000), $this->maker);
    $posted = $this->approvals->approve($pending, $this->checker);

    expect(fn () => app(BankEntryPostingService::class)->reverse($posted, $this->maker, 'Wrong company'))
        ->toThrow(BankRegisterException::class, 'different person');
    expect($posted->fresh()->status)->toBe(BankStatementEntry::STATUS_POSTED);

    expect(app(BankEntryPostingService::class)->reverse($posted, $this->checker, 'Wrong company')->status)
        ->toBe(BankStatementEntry::STATUS_REVERSED);
});

test('with the reversal setting off the maker reverses a large entry alone', function () {
    config()->set('bank.maker_checker.reversal_requires_approval', false);
    $posted = $this->approvals->approve($this->approvals->submit(classifiedEntry($this->fixture, 3000000), $this->maker), $this->checker);

    expect(app(BankEntryPostingService::class)->reverse($posted, $this->maker, 'Wrong company')->status)
        ->toBe(BankStatementEntry::STATUS_REVERSED);
});

test('the approve button is not offered to the person who submitted the entry', function () {
    $pending = $this->approvals->submit(classifiedEntry($this->fixture, 3000000), $this->checker);
    $approveUrl = route('bank-approvals.approve', $pending);

    $this->actingAs($this->checker)->get(route('bank-statement-entries.show', $pending))->assertOk()->assertDontSee($approveUrl, false);
    $this->actingAs($this->senior)->get(route('bank-statement-entries.show', $pending))->assertOk()->assertSee($approveUrl, false);
});

test('the waiting-for-approval tab of the register is the checker inbox, so there is no separate approvals page', function () {
    $pending = $this->approvals->submit(classifiedEntry($this->fixture, 3000000), $this->checker);
    $inbox = route('bank-statement-entries.index', ['filter' => ['status' => 'pending_approval']]);
    $approveUrl = route('bank-approvals.approve', $pending);

    // the person who submitted it is not offered the approve button, a different approver is
    $this->actingAs($this->checker)->get($inbox)->assertOk()->assertSee('Approval limits')->assertDontSee($approveUrl, false);
    $this->actingAs($this->senior)->get($inbox)->assertOk()->assertSee($approveUrl, false)->assertSee('Approve selected');

    expect(Route::has('bank-approvals.index'))->toBeFalse()
        ->and(Route::has('bank-statement-imports.index'))->toBeFalse();
});

test('ticked entries are approved together from the register', function () {
    $first = $this->approvals->submit(classifiedEntry($this->fixture, 3000000), $this->maker);
    $second = $this->approvals->submit(classifiedEntry($this->fixture, 4000000), $this->maker);

    $this->actingAs($this->checker)->post(route('bank-approvals.bulk-approve'), ['ids' => [$first->id, $second->id]])->assertSessionHas('success');

    expect($first->fresh()->status)->toBe(BankStatementEntry::STATUS_POSTED)
        ->and($second->fresh()->status)->toBe(BankStatementEntry::STATUS_POSTED);
});

test('an opening balance posts the bank against Opening Balance Equity, once per bank account', function () {
    $currency = $this->fixture['liveLedger']->currency_id;
    $equity = ChartOfAccount::create(['account_type_id' => $this->fixture['liveLedger']->account_type_id, 'currency_id' => $currency, 'account_code' => '3300', 'account_name' => 'Opening Balance Equity', 'normal_balance' => 'credit', 'is_active' => true]);
    // the migration adds the category; on a fresh database it has no account yet, so 3300 is found by its code
    $opening = BankEntryCategory::where('slug', 'opening-balance')->firstOrFail();
    $opening->update(['default_chart_of_account_id' => null]);
    // a booked deposit of the same amount on the same day must not be mistaken for the opening balance
    bookedElsewhere($this->fixture, 500000, now()->toDateString());

    $first = classifiedEntry($this->fixture, 500000);
    $first->update(['bank_entry_category_id' => $opening->id]);

    expect(app(BankEntryPostingService::class)->bookCandidates($first->fresh()))->toBeEmpty();

    $posted = $this->approvals->submit($first->fresh(), $this->maker);
    $lines = JournalEntryDetail::where('journal_entry_id', $posted->journal_entry_id)->get();

    expect($posted->status)->toBe(BankStatementEntry::STATUS_POSTED)
        ->and((float) $lines->firstWhere('chart_of_account_id', $this->fixture['liveLedger']->id)->debit)->toBe(500000.0)
        ->and((float) $lines->firstWhere('chart_of_account_id', $equity->id)->credit)->toBe(500000.0);

    $second = classifiedEntry($this->fixture, 1000);
    $second->update(['bank_entry_category_id' => $opening->id]);

    expect(fn () => $this->approvals->submit($second->fresh(), $this->maker))->toThrow(BankRegisterException::class, 'already has an opening balance');
});
