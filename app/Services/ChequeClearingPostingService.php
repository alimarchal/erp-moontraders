<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\CustomerEmployeeAccount;
use App\Models\CustomerEmployeeAccountTransaction;
use App\Models\JournalEntry;
use App\Models\SalesSettlementCheque;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Books a cleared settlement cheque out of Cheques in Hand (1122).
 *
 * The settlement put the cheque in 1122. When it clears, either the salesman brings the cash back and hands the cheque
 * back (Dr 1121 Cash), or, rarely, the cheque is deposited and cleared by the bank (Dr that bank's ledger). Changing
 * the status away from cleared reverses that journal, so 1122 always equals the cheques still pending. A pending
 * cheque that bounces leaves 1122 for the customer's debtors account (1112).
 */
class ChequeClearingPostingService
{
    public const VIA_CASH = 'cash';

    public const VIA_BANK = 'bank';

    public const CASH_CODE = '1121';

    public const CHEQUES_IN_HAND_CODE = '1122';

    public const DEBTORS_CODE = '1112';

    public const BOUNCE_TRANSACTION_TYPE = 'cheque_bounce';

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
     *
     * @return JournalEntry|null the reversing journal, or null when the cheque had no clearing journal
     */
    public function reverse(SalesSettlementCheque $cheque, string $toStatus): ?JournalEntry
    {
        if (! $cheque->clearing_journal_entry_id) {
            return null;
        }

        $reversal = $this->reverseOnce((int) $cheque->clearing_journal_entry_id, "REVERSAL: cheque {$cheque->cheque_number} changed from cleared to {$toStatus}");

        $cheque->forceFill(['clearing_journal_entry_id' => null])->saveQuietly();

        return $reversal;
    }

    /**
     * Book a bounced cheque back onto the customer: Dr 1112 Debtors / Cr 1122 Cheques in Hand. A bank charge, when the
     * bank took one, is put on the customer too: Dr 1112 / Cr the deposit bank (skipped when no deposit bank is known).
     * When the customer has an account with the settlement's salesman, the same amount is debited on that account.
     */
    public function postBounce(SalesSettlementCheque $cheque, string $date, float $bankCharge = 0, ?User $by = null): JournalEntry
    {
        if ($cheque->bounce_journal_entry_id) {
            throw new RuntimeException("Cheque {$cheque->cheque_number} is already booked as bounced.");
        }

        $cheque->loadMissing('salesSettlement', 'bankAccount', 'customer');

        if ($cheque->salesSettlement?->status !== 'posted') {
            throw new RuntimeException("Cheque {$cheque->cheque_number} belongs to a settlement that is not posted, so it is not in Cheques in Hand yet.");
        }

        $amount = round((float) $cheque->amount, 2);
        $bankCharge = $this->chargeableBankCharge($cheque, $bankCharge);
        $debtorsId = $this->accountId(self::DEBTORS_CODE);
        $description = "Cheque {$cheque->cheque_number} bounced".($cheque->customer ? " - {$cheque->customer->customer_name}" : '');

        $lines = [
            ['account_id' => $debtorsId, 'debit' => $amount, 'credit' => 0, 'description' => $description, 'cost_center_id' => 1],
            ['account_id' => $this->accountId(self::CHEQUES_IN_HAND_CODE), 'debit' => 0, 'credit' => $amount, 'description' => $description, 'cost_center_id' => 1],
        ];

        if ($bankCharge > 0) {
            $lines[] = ['account_id' => $debtorsId, 'debit' => $bankCharge, 'credit' => 0, 'description' => "Bank charge on bounced cheque {$cheque->cheque_number}", 'cost_center_id' => 1];
            $lines[] = ['account_id' => (int) $cheque->bankAccount->chart_of_account_id, 'debit' => 0, 'credit' => $bankCharge, 'description' => "Bank charge on bounced cheque {$cheque->cheque_number}", 'cost_center_id' => 1];
        }

        $result = $this->accounting->createJournalEntry([
            'entry_date' => $date,
            'reference' => 'CHQ-BOUNCE-'.$cheque->cheque_number,
            'description' => $description." ({$cheque->salesSettlement->settlement_number})",
            'reference_type' => SalesSettlementCheque::class,
            'reference_id' => $cheque->id,
            'lines' => $lines,
            'auto_post' => true,
        ]);

        if (! $result['success']) {
            throw new RuntimeException($result['message']);
        }

        $journal = $result['data'];
        $cheque->forceFill(['bounce_journal_entry_id' => $journal->id])->saveQuietly();
        $this->debitCustomerAccount($cheque, $journal, $date, round($amount + $bankCharge, 2), $by);

        return $journal;
    }

    /**
     * The bank charge a bounce books. A charge needs the deposit bank (linked to its ledger account) to credit.
     */
    public function chargeableBankCharge(SalesSettlementCheque $cheque, float $bankCharge): float
    {
        $bankCharge = round(max(0, $bankCharge), 2);

        if ($bankCharge <= 0) {
            return 0.0;
        }

        $cheque->loadMissing('bankAccount');

        if (! $cheque->bankAccount?->chart_of_account_id) {
            throw new RuntimeException('Choose the bank that charged the fee, or leave the bank charge empty.');
        }

        return $bankCharge;
    }

    /**
     * Take a bounce back (the bounced cheque went back to pending): reverse its journal and its customer ledger debit.
     *
     * @return JournalEntry|null the reversing journal, or null when the cheque had no bounce journal
     */
    public function reverseBounce(SalesSettlementCheque $cheque, ?User $by = null): ?JournalEntry
    {
        if (! $cheque->bounce_journal_entry_id) {
            return null;
        }

        $bounceJournalId = (int) $cheque->bounce_journal_entry_id;
        $reversal = $this->reverseOnce($bounceJournalId, "REVERSAL: bounce of cheque {$cheque->cheque_number} taken back");

        $bounceTransaction = CustomerEmployeeAccountTransaction::where('journal_entry_id', $bounceJournalId)
            ->where('transaction_type', self::BOUNCE_TRANSACTION_TYPE)
            ->whereDoesntHave('reversals')
            ->first();

        if ($bounceTransaction) {
            CustomerEmployeeAccountTransaction::create([
                'customer_employee_account_id' => $bounceTransaction->customer_employee_account_id,
                'transaction_date' => now()->toDateString(),
                'transaction_type' => 'adjustment',
                'reference_number' => 'REV-'.$bounceTransaction->reference_number,
                'sales_settlement_id' => $bounceTransaction->sales_settlement_id,
                'reverses_transaction_id' => $bounceTransaction->id,
                'description' => 'Reversal: '.$bounceTransaction->description,
                'debit' => $bounceTransaction->credit,
                'credit' => $bounceTransaction->debit,
                'payment_method' => $bounceTransaction->payment_method,
                'cheque_number' => $bounceTransaction->cheque_number,
                'cheque_date' => $bounceTransaction->cheque_date,
                'journal_entry_id' => $reversal->id,
                'created_by' => $by?->id ?? auth()->id(),
                'posted_at' => now(),
                'posted_by' => $by?->id ?? auth()->id(),
            ]);
        }

        $cheque->forceFill(['bounce_journal_entry_id' => null])->saveQuietly();

        return $reversal;
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

    /**
     * Reverse a journal today, or return the reversal already made for it.
     */
    private function reverseOnce(int $journalEntryId, string $description): JournalEntry
    {
        $existing = JournalEntry::where('reverses_entry_id', $journalEntryId)->first();

        if ($existing) {
            return $existing;
        }

        $result = $this->accounting->reverseJournalEntry($journalEntryId, $description, now()->toDateString());

        if (! $result['success']) {
            throw new RuntimeException($result['message']);
        }

        return $result['data'];
    }

    /**
     * Debit the bounced amount on the customer's account with the settlement's salesman, when that account exists.
     */
    private function debitCustomerAccount(SalesSettlementCheque $cheque, JournalEntry $journal, string $date, float $amount, ?User $by): void
    {
        $employeeId = $cheque->salesSettlement?->employee_id;

        if (! $cheque->customer_id || ! $employeeId) {
            return;
        }

        $account = CustomerEmployeeAccount::where('customer_id', $cheque->customer_id)->where('employee_id', $employeeId)->first();

        if (! $account) {
            return;
        }

        CustomerEmployeeAccountTransaction::create([
            'customer_employee_account_id' => $account->id,
            'transaction_date' => $date,
            'transaction_type' => self::BOUNCE_TRANSACTION_TYPE,
            'reference_number' => $cheque->cheque_number,
            'sales_settlement_id' => $cheque->sales_settlement_id,
            'description' => "Cheque {$cheque->cheque_number} bounced ({$cheque->salesSettlement->settlement_number})",
            'debit' => $amount,
            'credit' => 0,
            'payment_method' => 'cheque',
            'cheque_number' => $cheque->cheque_number,
            'cheque_date' => $cheque->cheque_date,
            'journal_entry_id' => $journal->id,
            'created_by' => $by?->id ?? auth()->id(),
            'posted_at' => now(),
            'posted_by' => $by?->id ?? auth()->id(),
        ]);
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
