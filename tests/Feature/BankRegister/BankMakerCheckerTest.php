<?php

use App\Models\BankEntryAuditLog;
use App\Models\BankEntryCategory;
use App\Models\BankStatementEntry;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Services\BankRegister\BankEntryApprovalService;
use App\Services\BankRegister\BankEntryPostingService;
use App\Services\BankRegister\BankRegisterException;
use App\Services\BankRegister\BankRegisterLedger;

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
        ->and($lines->firstWhere('chart_of_account_id', app(BankRegisterLedger::class)->companyAccount($this->fixture['company'])->id)->credit)->toBe('250000.00')
        ->and($posted->journalEntryDetail->chart_of_account_id)->toBe($this->fixture['bankLedger']->id);
});

test('a payment debits the company payable and credits the bank, with a BP receipt number', function () {
    $entry = classifiedEntry($this->fixture, 40000, 'debit');

    $posted = $this->approvals->submit($entry, $this->maker);

    $lines = $posted->journalEntry->details;
    expect($posted->receipt_no)->toStartWith('BP-')
        ->and($lines->firstWhere('chart_of_account_id', $this->fixture['bankLedger']->id)->credit)->toBe('40000.00')
        ->and($lines->firstWhere('chart_of_account_id', app(BankRegisterLedger::class)->companyAccount($this->fixture['company'])->id)->debit)->toBe('40000.00');
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

    $suspense = app(BankRegisterLedger::class)->account('unidentified_payments');
    $line = $posted->journalEntry->details->firstWhere('chart_of_account_id', $suspense->id);
    expect($line->debit)->toBe('9000.00')
        ->and($line->description)->toBe($posted->receipt_no.' | LANDLORD | CHQ 777 | October rent')
        ->and($posted->journalEntry->description)->toBe($line->description)
        ->and($posted->journalEntry->reference)->toBe($posted->receipt_no);
});

test('well known categories use their own register account', function () {
    $charges = BankStatementEntry::factory()->create([
        'bank_account_id' => $this->fixture['bank']->id, 'bank_entry_category_id' => BankEntryCategory::where('slug', 'bank-charges')->value('id'),
        'posting_date' => now()->toDateString(), 'credit' => 0, 'debit' => 1250, 'status' => 'classified',
    ]);

    $posted = $this->approvals->submit($charges, $this->maker);

    expect($posted->journalEntry->details->pluck('chart_of_account_id'))->toContain(app(BankRegisterLedger::class)->account('bank_charges')->id);
});

test('the live ledgers are never touched by a register journal', function () {
    $this->approvals->submit(classifiedEntry($this->fixture, 250000), $this->maker);
    $this->approvals->submit(classifiedEntry($this->fixture, 80000, 'debit'), $this->maker);

    $live = [$this->fixture['liveLedger']->id, $this->fixture['liveReceivable']->id, $this->fixture['livePayable']->id];

    expect(JournalEntryDetail::whereIn('chart_of_account_id', $live)->count())->toBe(0)
        ->and(JournalEntryDetail::count())->toBe(4);
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
