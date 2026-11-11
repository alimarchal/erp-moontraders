<?php

namespace App\Services\BankRegister;

use App\Models\BankEntryAuditLog;
use App\Models\BankStatementEntry;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Books a bank statement line in the live books, in one of two ways:
 *
 * - link: the money is already in the books (a settlement deposit, a supplier payment, a payroll payment...). The
 *   line is linked to that journal line on the bank's ledger; no journal is created, so nothing is counted twice.
 * - post: the money is in no other module (bank charges, bank tax, owner money, a transfer, an unknown receipt).
 *   A balanced journal is created: the bank's own ledger against the live account of the line's category.
 *
 * Posting is refused while the bank ledger has an unlinked journal line of the same amount and direction within
 * three days, unless the person confirms with a reason that it is a different transaction.
 */
class BankEntryPostingService
{
    public const MATCH_DAYS = 3;

    public const OPENING_BALANCE_SLUG = 'opening-balance';

    public function __construct(
        private readonly AccountingService $accounting,
        private readonly BankReceiptNumberGenerator $receipts,
        private readonly BankApprovalRules $approvalRules,
        private readonly BankRegisterAccounts $accounts,
    ) {}

    public function post(BankStatementEntry $entry, User $user): BankStatementEntry
    {
        return DB::transaction(function () use ($entry, $user) {
            $entry = BankStatementEntry::query()->lockForUpdate()->findOrFail($entry->id);

            if ($entry->isLocked()) {
                throw new BankRegisterException('This entry is already posted.');
            }

            if (! in_array($entry->status, [
                BankStatementEntry::STATUS_CLASSIFIED,
                BankStatementEntry::STATUS_PENDING_APPROVAL,
                BankStatementEntry::STATUS_REVERSED,
            ], true)) {
                throw new BankRegisterException('Only a classified entry can be posted.');
            }

            if (! $entry->bank_entry_category_id) {
                throw new BankRegisterException('Choose a category before posting.');
            }

            if (! $this->approvalRules->isFullyApproved($entry)) {
                throw new BankRegisterException('This entry still needs approval before it can be posted.');
            }

            $this->assertNotAlreadyBooked($entry);

            $entry->loadMissing(['bankAccount', 'category', 'company']);

            $bankLedger = $this->accounts->bankLedger($entry->bankAccount);
            $otherSide = $this->accounts->otherSide($entry);

            if ($otherSide->id === $bankLedger->id) {
                throw new BankRegisterException("The category's account is this bank's own ledger. Choose another account for the category in Settings › Bank categories.");
            }

            $receiptNo = $entry->receipt_no ?? $this->receipts->next($entry->direction, $entry->posting_date);
            $amount = $entry->amount;
            $note = $this->accounts->narration($entry, $receiptNo);

            $isReceipt = $entry->direction === 'credit';
            $lines = [
                ['account_id' => $bankLedger->id, 'debit' => $isReceipt ? $amount : 0, 'credit' => $isReceipt ? 0 : $amount, 'description' => $note, 'cost_center_id' => null],
                ['account_id' => $otherSide->id, 'debit' => $isReceipt ? 0 : $amount, 'credit' => $isReceipt ? $amount : 0, 'description' => $note, 'cost_center_id' => null],
            ];

            $result = $this->accounting->createJournalEntry([
                'entry_date' => $entry->posting_date->toDateString(),
                'reference' => $receiptNo,
                'description' => $note,
                'lines' => $lines,
                'auto_post' => true,
            ]);

            if (! $result['success']) {
                throw new BankRegisterException($result['message']);
            }

            $journalEntry = $result['data'];
            $detail = JournalEntryDetail::query()->where('journal_entry_id', $journalEntry->id)->where('line_no', 1)->firstOrFail();

            $entry->forceFill([
                'status' => BankStatementEntry::STATUS_POSTED,
                'receipt_no' => $receiptNo,
                'journal_entry_id' => $journalEntry->id,
                'journal_entry_detail_id' => $detail->id,
                'posted_by' => $user->id,
                'posted_at' => now(),
                'updated_by' => $user->id,
            ])->save();

            BankEntryAuditLog::record($entry, 'posted', null, ['journal_entry_id' => $journalEntry->id, 'receipt_no' => $receiptNo, 'account' => $otherSide->account_code], $user->id);

            return $entry;
        });
    }

    /**
     * Link a statement line to money already in the books. No journal is created.
     */
    public function link(BankStatementEntry $entry, JournalEntryDetail $detail, User $user): BankStatementEntry
    {
        return DB::transaction(function () use ($entry, $detail, $user) {
            $entry = BankStatementEntry::query()->lockForUpdate()->findOrFail($entry->id);
            $entry->loadMissing('bankAccount');

            if ($entry->isLocked() || $entry->status === BankStatementEntry::STATUS_PENDING_APPROVAL) {
                throw new BankRegisterException('Only an entry that is not posted or waiting for approval can be linked.');
            }

            if ($detail->chart_of_account_id !== $this->accounts->bankLedger($entry->bankAccount)->id) {
                throw new BankRegisterException("That journal line is not on {$entry->bankAccount->account_name}'s ledger.");
            }

            if ($detail->journalEntry?->status !== 'posted') {
                throw new BankRegisterException('That journal entry is not posted.');
            }

            $debitsBank = (float) $detail->debit > 0;

            if ($debitsBank !== ($entry->direction === 'credit')) {
                throw new BankRegisterException('The journal line goes the other way (money in and money out).');
            }

            if ($entry->amount - $this->unlinkedAmount($detail, $entry->id) > 0.009) {
                throw new BankRegisterException('The journal line has only '.number_format($this->unlinkedAmount($detail, $entry->id), 2).' left that is not linked to another line.');
            }

            $entry->forceFill([
                'status' => BankStatementEntry::STATUS_POSTED,
                'journal_entry_id' => $detail->journal_entry_id,
                'journal_entry_detail_id' => $detail->id,
                'posted_by' => $user->id,
                'posted_at' => now(),
                'updated_by' => $user->id,
            ])->save();

            BankEntryAuditLog::record($entry, 'linked_to_book', null, ['journal_entry_id' => $detail->journal_entry_id, 'journal_entry_detail_id' => $detail->id], $user->id);

            return $entry;
        });
    }

    /**
     * Take a booked line back out. A journal the register created is reversed; a line that was only linked to someone
     * else's journal is unlinked (that journal is not touched). Lines booked by a settlement follow the settlement.
     */
    public function reverse(BankStatementEntry $entry, User $user, string $reason): BankStatementEntry
    {
        return DB::transaction(function () use ($entry, $user, $reason) {
            $entry = BankStatementEntry::query()->lockForUpdate()->findOrFail($entry->id);

            if ($entry->status === BankStatementEntry::STATUS_RECONCILED) {
                throw new BankRegisterException('This entry is reconciled. Cancel the reconciliation before reversing it.');
            }

            if ($entry->status !== BankStatementEntry::STATUS_POSTED || ! $entry->journal_entry_id) {
                throw new BankRegisterException('Only a posted entry can be reversed.');
            }

            if ($entry->sales_settlement_id) {
                throw new BankRegisterException('This line is booked by its sales settlement. Revert the settlement instead; the line follows it.');
            }

            $old = ['journal_entry_id' => $entry->journal_entry_id, 'status' => $entry->status];

            if (! $entry->isBookedByRegister()) {
                $entry->forceFill([
                    'status' => $entry->bank_entry_category_id ? BankStatementEntry::STATUS_CLASSIFIED : BankStatementEntry::STATUS_UNCLASSIFIED,
                    'journal_entry_id' => null,
                    'journal_entry_detail_id' => null,
                    'posted_by' => null,
                    'posted_at' => null,
                    'updated_by' => $user->id,
                ])->save();

                BankEntryAuditLog::record($entry, 'unlinked_from_book', $old, ['reason' => $reason], $user->id);

                return $entry;
            }

            if ($this->approvalRules->reversalNeedsSecondPerson($entry, $user)) {
                throw new BankRegisterException('This entry needed approval to be posted, so the person who submitted it cannot reverse it alone. A different person with the reverse permission must reverse it.');
            }

            $result = $this->accounting->reverseJournalEntry($entry->journal_entry_id, "Reversal of {$entry->receipt_no}: {$reason}");

            if (! $result['success']) {
                throw new BankRegisterException($result['message']);
            }

            $entry->forceFill([
                'status' => BankStatementEntry::STATUS_REVERSED,
                'journal_entry_id' => null,
                'journal_entry_detail_id' => null,
                'updated_by' => $user->id,
            ])->save();

            BankEntryAuditLog::record($entry, 'reversed', $old, ['reversal_journal_entry_id' => $result['data']->id, 'reason' => $reason], $user->id);

            return $entry;
        });
    }

    /**
     * Journal lines on the bank's ledger that could already be this statement line: same direction, enough amount
     * not yet linked to another line, within three days. Settlement lines carry several deposits in one journal line,
     * so a line counts as long as the part still unlinked covers the amount.
     *
     * @return Collection<int, JournalEntryDetail> each with an extra "unlinked" amount
     */
    public function bookCandidates(BankStatementEntry $entry): Collection
    {
        $entry->loadMissing('bankAccount');

        // an opening balance is the bank's starting point, never a copy of a booked transaction
        if (! $entry->bankAccount?->chart_of_account_id || $entry->amount <= 0 || $this->isOpeningBalance($entry)) {
            return collect();
        }

        $column = $entry->direction === 'credit' ? 'debit' : 'credit';
        $from = $entry->posting_date->copy()->subDays(self::MATCH_DAYS)->toDateString();
        $to = $entry->posting_date->copy()->addDays(self::MATCH_DAYS)->toDateString();

        return JournalEntryDetail::query()
            ->with('journalEntry')
            ->where('chart_of_account_id', $entry->bankAccount->chart_of_account_id)
            ->where($column, '>=', $entry->amount - 0.009)
            ->where('journal_entry_id', '!=', (int) $entry->journal_entry_id)
            // a journal that was reversed, and the reversal itself, are no money in the bank
            ->whereHas('journalEntry', fn (Builder $q) => $q->where('status', 'posted')->whereNull('reverses_entry_id')
                ->whereNotIn('id', JournalEntry::query()->whereNotNull('reverses_entry_id')->select('reverses_entry_id'))
                ->whereDate('entry_date', '>=', $from)->whereDate('entry_date', '<=', $to))
            ->orderByRaw("ABS({$column} - ?)", [$entry->amount])
            ->limit(20)
            ->get()
            ->map(function (JournalEntryDetail $detail) use ($entry) {
                $detail->setAttribute('unlinked', $this->unlinkedAmount($detail, $entry->id));

                return $detail;
            })
            ->filter(fn (JournalEntryDetail $detail) => $detail->unlinked - $entry->amount > -0.009)
            ->values();
    }

    /**
     * Refuse to create a journal for money that looks already booked, unless the person said why it is different.
     */
    public function assertNotAlreadyBooked(BankStatementEntry $entry): void
    {
        if ($this->isOpeningBalance($entry)) {
            $this->assertSingleOpeningBalance($entry);

            return;
        }

        if (filled($entry->not_in_books_reason)) {
            return;
        }

        $candidate = $this->bookCandidates($entry)->first();

        if ($candidate) {
            throw new BankRegisterException(sprintf(
                'This money looks already booked: %s on %s (%s, %s). Use "Link to book entry" instead, or confirm it is a different transaction.',
                $candidate->journalEntry->reference ?: 'journal #'.$candidate->journal_entry_id,
                $candidate->journalEntry->entry_date?->format('d-m-Y'),
                number_format($entry->amount, 2),
                mb_strimwidth((string) $candidate->description, 0, 60, '…'),
            ));
        }
    }

    public function isOpeningBalance(BankStatementEntry $entry): bool
    {
        $entry->loadMissing('category');

        return $entry->category?->slug === self::OPENING_BALANCE_SLUG;
    }

    /**
     * A bank account starts once: a second opening balance (sent for approval or posted) is refused. A reversed one
     * no longer counts, so a wrong opening balance is reversed and entered again.
     */
    private function assertSingleOpeningBalance(BankStatementEntry $entry): void
    {
        $other = BankStatementEntry::query()
            ->where('bank_account_id', $entry->bank_account_id)
            ->where('id', '!=', $entry->id)
            ->whereIn('status', [BankStatementEntry::STATUS_PENDING_APPROVAL, BankStatementEntry::STATUS_POSTED, BankStatementEntry::STATUS_RECONCILED])
            ->whereHas('category', fn ($query) => $query->where('slug', self::OPENING_BALANCE_SLUG))
            ->first();

        if ($other) {
            throw new BankRegisterException('This bank account already has an opening balance (Rs '.number_format($other->amount, 2).' on '.$other->posting_date->format('d-m-Y').'). Reverse that one first if it is wrong.');
        }
    }

    /**
     * The part of a journal line not yet linked to a statement line.
     */
    public function unlinkedAmount(JournalEntryDetail $detail, ?int $exceptEntryId = null): float
    {
        $lineAmount = max((float) $detail->debit, (float) $detail->credit);
        $linked = (float) BankStatementEntry::query()
            ->where('journal_entry_detail_id', $detail->id)
            ->whereIn('status', [BankStatementEntry::STATUS_POSTED, BankStatementEntry::STATUS_RECONCILED])
            ->when($exceptEntryId, fn ($q) => $q->where('id', '!=', $exceptEntryId))
            ->sum(DB::raw('credit + debit'));

        return round($lineAmount - $linked, 2);
    }
}
