<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\SalesSettlementCheque;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Books a cleared settlement cheque out of Cheques in Hand (1122).
 *
 * The settlement put the cheque in 1122. When it clears, either the salesman brings the cash back and hands the cheque
 * back (Dr 1121 Cash), or, rarely, the cheque is deposited and cleared by the bank (Dr that bank's ledger). Changing
 * the status away from cleared reverses that journal, so 1122 always equals the cheques still pending.
 */
class ChequeClearingPostingService
{
    public const VIA_CASH = 'cash';

    public const VIA_BANK = 'bank';

    public const CASH_CODE = '1121';

    public const CHEQUES_IN_HAND_CODE = '1122';

    public function __construct(private AccountingService $accounting) {}

    public function post(SalesSettlementCheque $cheque): JournalEntry
    {
        // Cheques cleared before the cleared date was kept carry only the moment they were marked cleared.
        $clearedOn = $cheque->cleared_date ?? $cheque->status_updated_at;

        if (! $cheque->isCleared() || ! $clearedOn) {
            throw new RuntimeException("Cheque {$cheque->cheque_number} is not cleared.");
        }

        if ($cheque->clearing_journal_entry_id) {
            throw new RuntimeException("Cheque {$cheque->cheque_number} is already booked out of Cheques in Hand.");
        }

        $cheque->loadMissing('salesSettlement.employee', 'clearedBankAccount');

        if ($cheque->salesSettlement?->status !== 'posted') {
            throw new RuntimeException("Cheque {$cheque->cheque_number} belongs to a settlement that is not posted, so it is not in Cheques in Hand yet.");
        }

        $amount = round($cheque->clearedAmountOrFull(), 2);

        if ($amount <= 0) {
            throw new RuntimeException("Cheque {$cheque->cheque_number} has no cleared amount.");
        }

        $viaBank = $cheque->cleared_via === self::VIA_BANK;
        $salesman = $cheque->salesSettlement->employee?->name;
        $description = $viaBank
            ? "Cheque {$cheque->cheque_number} cleared by ".($cheque->clearedBankAccount?->account_name ?? 'bank')
            : "Cheque {$cheque->cheque_number} cleared - cash brought back".($salesman ? " by {$salesman}" : '');

        $result = $this->accounting->createJournalEntry([
            'entry_date' => $clearedOn->toDateString(),
            'reference' => 'CHQ-'.$cheque->cheque_number,
            'description' => $description." ({$cheque->salesSettlement->settlement_number})",
            'reference_type' => SalesSettlementCheque::class,
            'reference_id' => $cheque->id,
            'lines' => [
                ['account_id' => $viaBank ? $this->bankLedger($cheque->clearedBankAccount) : $this->accountId(self::CASH_CODE), 'debit' => $amount, 'credit' => 0, 'description' => $description, 'cost_center_id' => 1],
                ['account_id' => $this->accountId(self::CHEQUES_IN_HAND_CODE), 'debit' => 0, 'credit' => $amount, 'description' => $description, 'cost_center_id' => 1],
            ],
            'auto_post' => true,
        ]);

        if (! $result['success']) {
            throw new RuntimeException($result['message']);
        }

        $cheque->forceFill(['clearing_journal_entry_id' => $result['data']->id])->saveQuietly();

        return $result['data'];
    }

    /**
     * Take the clearing back (the cheque went back to pending, bounced or was cancelled).
     */
    public function reverse(SalesSettlementCheque $cheque, string $toStatus): void
    {
        if (! $cheque->clearing_journal_entry_id) {
            return;
        }

        $alreadyReversed = JournalEntry::where('reverses_entry_id', $cheque->clearing_journal_entry_id)->exists();

        if (! $alreadyReversed) {
            $result = $this->accounting->reverseJournalEntry(
                (int) $cheque->clearing_journal_entry_id,
                "REVERSAL: cheque {$cheque->cheque_number} changed from cleared to {$toStatus}",
                now()->toDateString(),
            );

            if (! $result['success']) {
                throw new RuntimeException($result['message']);
            }
        }

        $cheque->forceFill(['clearing_journal_entry_id' => null])->saveQuietly();
    }

    /**
     * Book every cleared cheque of a posted settlement that has no clearing journal yet. Those were cleared before
     * clearing was booked, always with cash brought back. A cheque without a cleared date is booked on the day it was
     * marked cleared; its cleared date stays empty so past cash reports do not change.
     *
     * @return array{cheques: int, amount: float}
     */
    public function postMissing(): array
    {
        $result = ['cheques' => 0, 'amount' => 0.0];

        $cheques = SalesSettlementCheque::query()
            ->where('status', 'cleared')
            ->where(fn ($query) => $query->whereNotNull('cleared_date')->orWhereNotNull('status_updated_at'))
            ->whereNull('clearing_journal_entry_id')
            ->whereHas('salesSettlement', fn ($query) => $query->where('status', 'posted'))
            ->orderByRaw('COALESCE(cleared_date, status_updated_at)')->orderBy('id')
            ->get();

        foreach ($cheques as $cheque) {
            DB::transaction(function () use ($cheque, &$result) {
                if (! $cheque->cleared_via) {
                    $cheque->forceFill(['cleared_via' => self::VIA_CASH])->saveQuietly();
                }

                $this->post($cheque);
                $result['cheques']++;
                $result['amount'] += $cheque->clearedAmountOrFull();
            });
        }

        $result['amount'] = round($result['amount'], 2);

        return $result;
    }

    private function bankLedger(?BankAccount $bank): int
    {
        if (! $bank?->chart_of_account_id) {
            throw new RuntimeException('Choose the bank the cheque was deposited in; it must be linked to its ledger account in Settings › Bank Accounts.');
        }

        return (int) $bank->chart_of_account_id;
    }

    private function accountId(string $code): int
    {
        return (int) (ChartOfAccount::where('account_code', $code)->value('id')
            ?? throw new RuntimeException("Account {$code} is missing from the chart of accounts."));
    }
}
