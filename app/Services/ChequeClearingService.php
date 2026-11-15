<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\ChequeClearing;
use App\Models\SalesSettlementCheque;
use App\Models\User;
use App\Support\CashNotes;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Clears settlement cheques in batches and moves them between pending, bounced and cancelled.
 *
 * A clearing is one batch (a single clearing is a batch of one). A cash batch is one company and holds the notes
 * counted for it; a bank batch holds no notes. A cleared cheque can be changed only on the day it was cleared, and
 * changing one cheque reopens its whole batch. Every change writes a history row naming the journals it posted or
 * reversed, so each cheque's trail can be followed to the ledger.
 */
class ChequeClearingService
{
    public function __construct(private ChequeClearingPostingService $posting) {}

    /**
     * @param  Collection<int, SalesSettlementCheque>  $cheques
     */
    public function clear(Collection $cheques, string $clearedDate, string $via, ?int $bankAccountId, CashNotes $notes, ?string $remarks, User $by): ChequeClearing
    {
        return DB::transaction(function () use ($cheques, $clearedDate, $via, $bankAccountId, $notes, $remarks, $by) {
            $locked = $this->lock($cheques->pluck('id'));
            $byCash = $via === ChequeClearingPostingService::VIA_CASH;

            if ($locked->count() !== $cheques->count()) {
                throw new RuntimeException('Some of the selected cheques no longer exist. Reload the page and select again.');
            }

            $notPending = $locked->reject(fn (SalesSettlementCheque $cheque) => $cheque->isPending());

            if ($notPending->isNotEmpty()) {
                throw new RuntimeException('Only pending cheques can be cleared. Already '.$notPending->pluck('status')->unique()->implode(' / ').': '.$notPending->pluck('cheque_number')->implode(', ').'.');
            }

            // A cheque without a company counts as its own company, so a cash batch is always exactly one company.
            $companies = $locked->map(fn (SalesSettlementCheque $cheque) => $cheque->salesSettlement?->supplier)
                ->unique(fn ($supplier) => $supplier?->id ?? 'none')
                ->sortBy(fn ($supplier) => $supplier ? '0'.$supplier->supplier_name : '1')
                ->values();
            $suppliers = $companies->filter()->values();

            if ($byCash && ($companies->count() > 1 || $suppliers->isEmpty())) {
                throw new RuntimeException('Cash clearing must be one company at a time. Selected: '.$companies->map(fn ($supplier) => $supplier?->supplier_name ?? 'No company')->implode(', ').'.');
            }

            $this->guardDate($locked, $clearedDate);

            $total = round((float) $locked->sum('amount'), 2);

            if ($byCash && abs($notes->total() - $total) > 0.009) {
                throw new RuntimeException($notes->total() > 0
                    ? 'The notes you counted add up to Rs '.number_format($notes->total(), 2).' but the '.($locked->count() === 1 ? 'cheque is' : $locked->count().' cheques come to').' Rs '.number_format($total, 2).'. Make them equal.'
                    : 'Count the cash notes brought back; they must add up to Rs '.number_format($total, 2).'.');
            }

            if (! $byCash && ! $bankAccountId) {
                throw new RuntimeException('Choose the bank the cheques were deposited in.');
            }

            $bankId = $byCash ? null : $bankAccountId;
            $batch = ChequeClearing::create(($byCash ? $notes->columns() : CashNotes::fromInput([])->columns()) + [
                'cleared_date' => $clearedDate,
                'cleared_via' => $via,
                'bank_account_id' => $bankId,
                'supplier_id' => $companies->count() === 1 ? $suppliers->first()?->id : null,
                'notes' => $remarks,
                'created_by' => $by->id,
            ]);

            $denominations = $byCash ? $batch->only(array_keys($notes->columns())) : null;
            $bankName = $bankId ? BankAccount::whereKey($bankId)->value('account_name') : null;
            $tag = $byCash
                ? "[Cash brought back · batch #{$batch->id}]"
                : '[Cleared by bank'.($bankName ? ": {$bankName}" : '')." · batch #{$batch->id}]";

            foreach ($locked as $cheque) {
                $cheque->update([
                    'status' => 'cleared',
                    'cleared_date' => $clearedDate,
                    'cleared_amount' => $cheque->amount,
                    'cleared_via' => $via,
                    'cleared_bank_account_id' => $bankId,
                    'cheque_clearing_id' => $batch->id,
                    'status_updated_at' => now(),
                ]);

                $journal = $this->posting->post($cheque->fresh());

                $this->record($cheque, 'pending', 'cleared', $by, $tag, $remarks, [
                    'cleared_date' => $clearedDate,
                    'cleared_amount' => $cheque->amount,
                    'cleared_via' => $via,
                    'bank_account_id' => $bankId,
                    'cheque_clearing_id' => $batch->id,
                    'journal_entry_id' => $journal->id,
                    'denominations' => $denominations,
                ]);
            }

            return $batch;
        });
    }

    /**
     * Move a cheque to pending, bounced or cancelled. A cleared cheque is first un-cleared (same day only, reopening
     * its batch); a pending cheque that bounces is booked onto the customer; a bounced cheque that goes back to
     * pending has its bounce taken back.
     */
    public function changeStatus(SalesSettlementCheque $cheque, string $status, ?string $date, ?float $bankCharge, ?string $remarks, User $by): void
    {
        if (! in_array($status, ['pending', 'bounced', 'cancelled'], true)) {
            throw new RuntimeException('Use the clearing form to clear a cheque.');
        }

        DB::transaction(function () use ($cheque, $status, $date, $bankCharge, $remarks, $by) {
            $cheque = $this->lock(collect([$cheque->id]))->first()
                ?? throw new RuntimeException('This cheque no longer exists. Reload the page.');

            if ($cheque->status === $status) {
                throw new RuntimeException("This cheque is already {$status}.");
            }

            if ($cheque->isCleared()) {
                $this->unclear($cheque, $by, $status === 'pending' ? $remarks : null);
                $cheque->refresh();

                if ($status === 'pending') {
                    return;
                }
            }

            $fromStatus = $cheque->status;
            $journal = null;
            $reversal = null;
            $reversedId = null;
            $charge = null;

            if ($status === 'bounced' && ! $cheque->bounce_journal_entry_id) {
                $bouncedOn = $date ?? now()->toDateString();
                $this->guardDate(collect([$cheque]), $bouncedOn, 'Bounce date');
                $charge = $this->posting->chargeableBankCharge($cheque, (float) ($bankCharge ?? 0)) ?: null;
                $journal = $this->posting->postBounce($cheque, $bouncedOn, (float) ($bankCharge ?? 0), $by);
            } elseif ($status === 'pending' && $cheque->bounce_journal_entry_id) {
                // Whatever the cheque went through since (e.g. bounced, then cancelled), a pending cheque is back in 1122.
                $reversedId = $cheque->bounce_journal_entry_id;
                $reversal = $this->posting->reverseBounce($cheque, $by);
            }

            $cheque->update(['status' => $status, 'status_updated_at' => now()]);

            $this->record($cheque, $fromStatus, $status, $by, null, $remarks, [
                'journal_entry_id' => $journal?->id ?? $reversal?->id,
                'reversed_journal_entry_id' => $reversal ? $reversedId : null,
                'bank_charge' => $charge,
            ]);
        });
    }

    /**
     * Take a cleared cheque back to pending on the day it was cleared, or on the day the clearing was entered. Its whole
     * batch reopens: every cheque in it goes back to pending with its clearing journal reversed and its own history
     * row, and the batch is deleted.
     */
    private function unclear(SalesSettlementCheque $cheque, User $by, ?string $remarks): void
    {
        $clearedOn = $cheque->cleared_date ?? $cheque->status_updated_at;
        $enteredToday = $cheque->status_updated_at instanceof CarbonInterface && $cheque->status_updated_at->isToday();

        // A wrong back-dated clearing can still be fixed on the day it was entered.
        if (! $enteredToday && (! $clearedOn instanceof CarbonInterface || ! $clearedOn->isToday())) {
            throw new RuntimeException('This cheque was cleared on '.($clearedOn ? Carbon::parse($clearedOn)->format('d-M-Y') : 'an earlier day').'; it can only be changed on that day.');
        }

        $batch = $cheque->cheque_clearing_id ? ChequeClearing::lockForUpdate()->find($cheque->cheque_clearing_id) : null;
        $cheques = $batch
            ? $this->lock(SalesSettlementCheque::where('cheque_clearing_id', $batch->id)->pluck('id'))
            : collect([$cheque]);
        $denominations = $batch?->cleared_via === ChequeClearingPostingService::VIA_CASH
            ? $batch->only(array_keys(CashNotes::fromInput([])->columns()))
            : null;

        foreach ($cheques as $member) {
            $isTarget = $member->is($cheque);
            $clearingJournalId = $member->clearing_journal_entry_id;
            $reversal = $this->posting->reverse($member, 'pending');
            $tag = $batch
                ? ($isTarget ? "[Un-cleared · batch #{$batch->id} reopened]" : "[Un-cleared with batch #{$batch->id}: cheque {$cheque->cheque_number} was un-cleared]")
                : '[Un-cleared]';

            $this->record($member, 'cleared', 'pending', $by, $tag, $isTarget ? $remarks : null, [
                'cleared_via' => $member->cleared_via,
                'bank_account_id' => $member->cleared_bank_account_id,
                'cheque_clearing_id' => $batch?->id,
                'journal_entry_id' => $reversal?->id,
                'reversed_journal_entry_id' => $clearingJournalId,
                'denominations' => $denominations,
            ]);

            $member->update([
                'status' => 'pending',
                'cleared_date' => null,
                'cleared_amount' => null,
                'cleared_via' => null,
                'cleared_bank_account_id' => null,
                'cheque_clearing_id' => null,
                'status_updated_at' => now(),
            ]);
        }

        $batch?->delete();
    }

    /**
     * A cheque can be cleared or bounced only on or after its settlement day, and not in the future.
     *
     * @param  Collection<int, SalesSettlementCheque>  $cheques
     */
    private function guardDate(Collection $cheques, string $date, string $label = 'Cleared date'): void
    {
        $latest = $cheques->map(fn (SalesSettlementCheque $cheque) => $cheque->salesSettlement?->settlement_date)->filter()->max();
        $day = Carbon::parse($date)->startOfDay();

        if ($day->isAfter(today()) || ($latest && $day->lt(Carbon::parse($latest)->startOfDay()))) {
            throw new RuntimeException("{$label} must be between ".($latest ? Carbon::parse($latest)->format('d-M-Y') : 'the settlement date').' and today.');
        }
    }

    /**
     * @param  Collection<int, int>  $ids
     * @return Collection<int, SalesSettlementCheque>
     */
    private function lock(Collection $ids): Collection
    {
        return SalesSettlementCheque::with('salesSettlement.supplier')->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function record(SalesSettlementCheque $cheque, ?string $fromStatus, string $toStatus, User $by, ?string $tag, ?string $remarks, array $details): void
    {
        $cheque->histories()->create($details + [
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'notes' => trim(($tag ?? '').' '.($remarks ?? '')) ?: null,
            'changed_by' => $by->id,
        ]);
    }
}
