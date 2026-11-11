<?php

namespace App\Services\BankRegister;

use App\Models\BankAccount;
use App\Models\BankEntryAuditLog;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationItem;
use App\Models\BankStatementEntry;
use App\Models\JournalEntryDetail;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Period reconciliation of a bank account: statement lines are matched with journal lines on the bank's own live
 * ledger (the one settlement, supplier payments, payroll and the register post to); when everything is matched and
 * the difference is zero the period is locked. The match lives in bank_reconciliation_items: posted journal lines are
 * immutable (database trigger), so their own reconciliation_status column is deliberately left alone.
 *
 * A settlement books all its deposits into one bank account as one journal line, so several statement lines can be
 * matched with one journal line; only the part of a journal line not matched yet counts as in transit / outstanding.
 */
class BankReconciliationService
{
    public function __construct(
        private readonly BankRegisterAccounts $accounts,
        private readonly BankEntryPostingService $posting,
    ) {}

    public function start(
        BankAccount $account,
        CarbonInterface $from,
        CarbonInterface $to,
        float $statementBalance,
        float $openingBalance,
        ?string $notes,
        User $user,
    ): BankReconciliation {
        if ($to->lt($from)) {
            throw new BankRegisterException('The period end cannot be before the period start.');
        }

        $overlap = BankReconciliation::query()
            ->where('bank_account_id', $account->id)
            ->where('status', '!=', BankReconciliation::STATUS_CANCELLED)
            ->whereDate('period_from', '<=', $to)
            ->whereDate('period_to', '>=', $from)
            ->exists();

        if ($overlap) {
            throw new BankRegisterException('A reconciliation already covers part of this period for this account.');
        }

        return DB::transaction(function () use ($account, $from, $to, $statementBalance, $openingBalance, $notes, $user) {
            $reconciliation = BankReconciliation::create([
                'bank_account_id' => $account->id,
                'period_from' => $from->toDateString(),
                'period_to' => $to->toDateString(),
                'statement_date' => $to->toDateString(),
                'statement_balance' => $statementBalance,
                'opening_balance' => $openingBalance,
                'book_balance' => 0,
                'difference' => 0,
                'status' => BankReconciliation::STATUS_IN_PROGRESS,
                'notes' => $notes,
                'created_by' => $user->id,
            ]);

            $this->autoMatch($reconciliation, $user);
            $this->refreshTotals($reconciliation);

            return $reconciliation->refresh();
        });
    }

    /**
     * Link every posted statement line in the period to the journal line it created.
     *
     * @return int number of lines matched
     */
    public function autoMatch(BankReconciliation $reconciliation, User $user): int
    {
        $this->assertOpen($reconciliation);

        $matched = 0;

        $this->unmatchedEntries($reconciliation)
            ->where('status', BankStatementEntry::STATUS_POSTED)
            ->whereNotNull('journal_entry_detail_id')
            ->get()
            ->each(function (BankStatementEntry $entry) use ($reconciliation, $user, &$matched) {
                $this->createItem($reconciliation, $entry, $entry->journal_entry_detail_id, 'auto', $user);
                $matched++;
            });

        $this->refreshTotals($reconciliation);

        return $matched;
    }

    /**
     * Match a statement line with a journal line already in the books (e.g. a settlement bank slip). A line that is not
     * booked yet is linked to that journal line first; no journal is created.
     */
    public function match(BankReconciliation $reconciliation, BankStatementEntry $entry, JournalEntryDetail $detail, User $user): void
    {
        $this->assertOpen($reconciliation);

        if ($detail->chart_of_account_id !== $this->ledgerId($reconciliation)) {
            throw new BankRegisterException('The journal line is not on this account\'s ledger.');
        }

        DB::transaction(function () use ($reconciliation, $entry, $detail, $user) {
            if ($entry->status !== BankStatementEntry::STATUS_POSTED) {
                $entry = $this->posting->link($entry, $detail, $user);
            } elseif ((int) $entry->journal_entry_detail_id !== $detail->id) {
                throw new BankRegisterException('This statement line is already booked on another journal line.');
            }

            $this->createItem($reconciliation, $entry, $detail->id, 'manual', $user);
            $this->refreshTotals($reconciliation);
        });
    }

    public function unmatch(BankReconciliation $reconciliation, BankReconciliationItem $item, User $user): void
    {
        $this->assertOpen($reconciliation);

        DB::transaction(function () use ($reconciliation, $item, $user) {
            $entry = $item->entry;

            // A manual link to an existing journal line is undone completely; posted and settlement lines stay booked.
            if ($item->match_type === 'manual' && ! $entry->receipt_no && ! $entry->sales_settlement_id) {
                $entry->forceFill([
                    'status' => $entry->bank_entry_category_id ? BankStatementEntry::STATUS_CLASSIFIED : BankStatementEntry::STATUS_UNCLASSIFIED,
                    'journal_entry_id' => null,
                    'journal_entry_detail_id' => null,
                    'posted_by' => null,
                    'posted_at' => null,
                ])->save();
                BankEntryAuditLog::record($entry, 'unlinked_from_book', null, null, $user->id);
            }

            $item->delete();
            $this->refreshTotals($reconciliation);
        });
    }

    /**
     * Lock the period: needs every statement line matched and a zero difference.
     */
    public function complete(BankReconciliation $reconciliation, User $user): BankReconciliation
    {
        $this->assertOpen($reconciliation);

        return DB::transaction(function () use ($reconciliation, $user) {
            $totals = $this->refreshTotals($reconciliation);

            if ($totals['unmatched_entries']->isNotEmpty()) {
                throw new BankRegisterException($totals['unmatched_entries']->count().' statement line(s) are still unmatched.');
            }

            if (abs($totals['difference']) >= 0.01) {
                throw new BankRegisterException('The difference is '.number_format($totals['difference'], 2).'. It must be zero before the period can be locked.');
            }

            $entryIds = $reconciliation->items()->pluck('bank_statement_entry_id');

            BankStatementEntry::whereIn('id', $entryIds)->update([
                'status' => BankStatementEntry::STATUS_RECONCILED,
                'bank_reconciliation_id' => $reconciliation->id,
            ]);

            $reconciliation->forceFill([
                'status' => BankReconciliation::STATUS_COMPLETED,
                'completed_by' => $user->id,
                'completed_at' => now(),
            ])->save();

            return $reconciliation;
        });
    }

    /**
     * Cancel an open reconciliation, or undo a completed one (lines go back to "posted").
     */
    public function cancel(BankReconciliation $reconciliation, User $user): BankReconciliation
    {
        if ($reconciliation->status === BankReconciliation::STATUS_CANCELLED) {
            throw new BankRegisterException('This reconciliation is already cancelled.');
        }

        return DB::transaction(function () use ($reconciliation) {
            $entryIds = $reconciliation->items()->pluck('bank_statement_entry_id');

            BankStatementEntry::whereIn('id', $entryIds)->where('status', BankStatementEntry::STATUS_RECONCILED)->update([
                'status' => BankStatementEntry::STATUS_POSTED,
            ]);
            BankStatementEntry::whereIn('id', $entryIds)->update(['bank_reconciliation_id' => null]);

            $reconciliation->items()->delete();
            $reconciliation->forceFill(['status' => BankReconciliation::STATUS_CANCELLED])->save();

            return $reconciliation;
        });
    }

    /**
     * Figures shown on the reconciliation screen, also written back to the record while it is open.
     *
     * @return array{
     *     book_balance: float, adjusted_statement: float, difference: float, reconciled_total: float,
     *     deposits_in_transit: float, outstanding_payments: float,
     *     unmatched_entries: Collection<int, BankStatementEntry>, unmatched_book_lines: Collection<int, JournalEntryDetail>
     * }
     */
    public function refreshTotals(BankReconciliation $reconciliation): array
    {
        $ledgerId = $this->ledgerId($reconciliation);

        $bookBalance = (float) JournalEntryDetail::query()
            ->where('chart_of_account_id', $ledgerId)
            ->whereHas('journalEntry', fn (Builder $q) => $q->where('status', 'posted')->whereDate('entry_date', '<=', $reconciliation->period_to))
            ->sum(DB::raw('debit - credit'));

        $unmatchedBookLines = $this->openBookLines($reconciliation, $ledgerId);

        $depositsInTransit = round((float) $unmatchedBookLines->where('debit', '>', 0)->sum('open_amount'), 2);
        $outstandingPayments = round((float) $unmatchedBookLines->where('credit', '>', 0)->sum('open_amount'), 2);
        $adjustedStatement = round((float) $reconciliation->statement_balance + $depositsInTransit - $outstandingPayments, 2);
        $difference = round($adjustedStatement - $bookBalance, 2);

        $reconciledTotal = (float) $reconciliation->items()
            ->join('bank_statement_entries as e', 'e.id', '=', 'bank_reconciliation_items.bank_statement_entry_id')
            ->sum(DB::raw('e.credit - e.debit'));

        if ($reconciliation->status === BankReconciliation::STATUS_IN_PROGRESS) {
            $reconciliation->forceFill([
                'book_balance' => $bookBalance,
                'difference' => $difference,
                'reconciled_total' => $reconciledTotal,
            ])->save();
        }

        return [
            'book_balance' => $bookBalance,
            'adjusted_statement' => $adjustedStatement,
            'difference' => $difference,
            'reconciled_total' => $reconciledTotal,
            'deposits_in_transit' => $depositsInTransit,
            'outstanding_payments' => $outstandingPayments,
            'unmatched_entries' => $this->unmatchedEntries($reconciliation)->orderBy('posting_date')->get(),
            'unmatched_book_lines' => $unmatchedBookLines,
        ];
    }

    /**
     * Book lines that could be the other half of a statement line (same direction, enough unmatched amount, within 3 days).
     *
     * @return Collection<int, JournalEntryDetail>
     */
    public function suggestions(BankReconciliation $reconciliation, BankStatementEntry $entry): Collection
    {
        return $this->posting->bookCandidates($entry);
    }

    /**
     * Journal lines on the ledger, from the first reconciled period of the account to the end of this one, whose amount
     * is not fully matched with statement lines yet. Each carries the part still open as "open_amount".
     *
     * @return Collection<int, JournalEntryDetail>
     */
    private function openBookLines(BankReconciliation $reconciliation, int $ledgerId): Collection
    {
        $from = BankReconciliation::query()
            ->where('bank_account_id', $reconciliation->bank_account_id)
            ->where('status', '!=', BankReconciliation::STATUS_CANCELLED)
            ->min('period_from') ?? $reconciliation->period_from;

        $matched = BankReconciliationItem::query()
            ->join('bank_statement_entries as e', 'e.id', '=', 'bank_reconciliation_items.bank_statement_entry_id')
            ->whereNotNull('bank_reconciliation_items.journal_entry_detail_id')
            ->groupBy('bank_reconciliation_items.journal_entry_detail_id')
            ->selectRaw('bank_reconciliation_items.journal_entry_detail_id as detail_id, SUM(e.credit + e.debit) as amount')
            ->pluck('amount', 'detail_id');

        return JournalEntryDetail::query()
            ->with('journalEntry')
            ->where('chart_of_account_id', $ledgerId)
            ->whereHas('journalEntry', fn (Builder $q) => $q->where('status', 'posted')
                ->whereDate('entry_date', '>=', $from)
                ->whereDate('entry_date', '<=', $reconciliation->period_to))
            ->get()
            ->map(function (JournalEntryDetail $detail) use ($matched) {
                $detail->setAttribute('open_amount', round(max((float) $detail->debit, (float) $detail->credit) - (float) ($matched[$detail->id] ?? 0), 2));

                return $detail;
            })
            ->filter(fn (JournalEntryDetail $detail) => $detail->open_amount > 0.009)
            ->values();
    }

    private function ledgerId(BankReconciliation $reconciliation): int
    {
        return $this->accounts->bankLedger($reconciliation->bankAccount()->firstOrFail())->id;
    }

    /**
     * @return Builder<BankStatementEntry>
     */
    private function unmatchedEntries(BankReconciliation $reconciliation): Builder
    {
        return BankStatementEntry::query()
            ->where('bank_account_id', $reconciliation->bank_account_id)
            ->whereDate('posting_date', '>=', $reconciliation->period_from)
            ->whereDate('posting_date', '<=', $reconciliation->period_to)
            // a settlement line reversed with its settlement is no money in the bank, unless a statement showed it
            ->where(fn (Builder $q) => $q->whereNull('sales_settlement_id')->orWhere('status', '!=', BankStatementEntry::STATUS_REVERSED)->orWhereNotNull('import_id'))
            ->whereDoesntHave('reconciliationItems');
    }

    private function createItem(BankReconciliation $reconciliation, BankStatementEntry $entry, ?int $detailId, string $type, User $user): void
    {
        BankReconciliationItem::create([
            'bank_reconciliation_id' => $reconciliation->id,
            'bank_statement_entry_id' => $entry->id,
            'journal_entry_detail_id' => $detailId,
            'match_type' => $type,
            'matched_by' => $user->id,
            'matched_at' => now(),
        ]);
    }

    private function assertOpen(BankReconciliation $reconciliation): void
    {
        if ($reconciliation->status !== BankReconciliation::STATUS_IN_PROGRESS) {
            throw new BankRegisterException('This reconciliation is '.$reconciliation->status.' and can no longer be changed.');
        }
    }
}
