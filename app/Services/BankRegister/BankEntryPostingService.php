<?php

namespace App\Services\BankRegister;

use App\Models\BankEntryAuditLog;
use App\Models\BankStatementEntry;
use App\Models\JournalEntryDetail;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Support\Facades\DB;

/**
 * Turns a classified statement line into a balanced double-entry journal and takes it back out again.
 * Bank side: the bank account's ledger account. Other side: the category's default account, else the
 * company's receivable (money in) or payable (money out) account.
 */
class BankEntryPostingService
{
    public function __construct(
        private readonly AccountingService $accounting,
        private readonly BankReceiptNumberGenerator $receipts,
        private readonly BankApprovalRules $approvalRules,
        private readonly BankRegisterLedger $ledger,
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

            $entry->loadMissing(['bankAccount', 'category', 'company']);

            // Only the register's own accounts are used, so GRN, settlement and every other live balance is untouched.
            $bankLedger = $this->ledger->ledgerForBank($entry->bankAccount);
            $otherSide = $this->ledger->otherSide($entry);
            $receiptNo = $entry->receipt_no ?? $this->receipts->next($entry->direction, $entry->posting_date);
            $amount = $entry->amount;
            $note = $this->ledger->narration($entry, $receiptNo);

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

            BankEntryAuditLog::record($entry, 'posted', null, ['journal_entry_id' => $journalEntry->id, 'receipt_no' => $receiptNo], $user->id);

            return $entry;
        });
    }

    /**
     * Reverse a posted line: a reversing journal is booked and the line goes back for re-classification.
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

            $result = $this->accounting->reverseJournalEntry($entry->journal_entry_id, "Reversal of {$entry->receipt_no}: {$reason}");

            if (! $result['success']) {
                throw new BankRegisterException($result['message']);
            }

            $old = ['journal_entry_id' => $entry->journal_entry_id, 'status' => $entry->status];

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
}
