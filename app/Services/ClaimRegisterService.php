<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\ClaimRegister;
use App\Models\JournalEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClaimRegisterService
{
    public function __construct(private AccountingService $accountingService) {}

    /**
     * Create a claim register entry (draft — no GL posting).
     *
     * @param  array<string, mixed>  $data  Validated request data
     * @return array{success: bool, data: ?ClaimRegister, message: string}
     */
    public function createClaim(array $data): array
    {
        try {
            $claim = ClaimRegister::create($data);

            return [
                'success' => true,
                'data' => $claim,
                'message' => "Claim '{$claim->reference_number}' created successfully.",
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create claim register', [
                'error' => $e->getMessage(),
                'data' => $data,
                'user_id' => auth()->id(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to create claim: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Update a claim register entry (no GL re-posting).
     *
     * @param  array<string, mixed>  $data  Validated request data
     * @return array{success: bool, data: ?ClaimRegister, message: string}
     */
    public function updateClaim(ClaimRegister $claim, array $data): array
    {
        try {
            $claim->update($data);

            return [
                'success' => true,
                'data' => $claim->fresh(),
                'message' => "Claim '{$claim->reference_number}' updated successfully.",
            ];
        } catch (\Exception $e) {
            Log::error('Failed to update claim register', [
                'claim_id' => $claim->id,
                'error' => $e->getMessage(),
                'data' => $data,
                'user_id' => auth()->id(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to update claim: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Post a claim to the GL — creates a journal entry and marks the claim as posted.
     *
     * Claim Raised (debit > 0):
     *   DR debit_account (e.g. 1112 Pending Claims Debtors)   = debit amount
     *   CR credit_account (e.g. Income account) = debit amount
     *
     * Recovery Received (credit > 0):
     *   DR debit_account (e.g. Bank/Cash)       = credit amount
     *   CR credit_account (e.g. 1112 Pending Claims Debtors)   = credit amount
     *
     * @return array{success: bool, data: ?ClaimRegister, message: string}
     */
    public function postClaim(ClaimRegister $claim): array
    {
        try {
            DB::beginTransaction();

            if ($claim->isPosted()) {
                throw new \Exception('Claim is already posted.');
            }

            // The recovery is in the books through the Supplier Ledger Register's claim adjustment (Dr Creditors /
            // Cr Pending Claims); a journal here too would take the same claim off twice.
            if ($claim->isLedgerAdjustment()) {
                $claim->update(['posted_at' => now(), 'posted_by' => auth()->id()]);
                DB::commit();

                return [
                    'success' => true,
                    'data' => $claim->fresh(),
                    'message' => 'Recovery recorded. It reaches the books through the claim adjustment in the Supplier Ledger Register, so no second journal is made.',
                ];
            }

            // Both GL accounts must be set
            if (! $claim->debit_account_id || ! $claim->credit_account_id) {
                throw new \Exception('Debit and Credit accounts must be set before posting.');
            }

            // Resolve the credit account — if payment is via bank_transfer and a bank
            // account is selected, use the bank account's linked COA instead
            $creditAccountId = $this->resolveCreditAccount($claim);

            $journalEntry = $this->createClaimJournalEntry($claim, $creditAccountId);

            if (! $journalEntry) {
                throw new \Exception('Failed to create journal entry — check GL account configuration.');
            }

            $claim->update([
                'posted_at' => now(),
                'posted_by' => auth()->id(),
                'journal_entry_id' => $journalEntry->id,
            ]);

            DB::commit();

            return [
                'success' => true,
                'data' => $claim->fresh(),
                'message' => "Claim '{$claim->reference_number}' posted successfully with journal entry.",
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to post claim register', [
                'claim_id' => $claim->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to post claim: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Resolve the credit account for journal entry creation.
     *
     * For CLAIM (supplier owes us): Credit goes to Income account (4230 Pending Claim Register Allowance)
     * For RECOVERY (we receive payment): Credit goes to 1112 Pending Claims Debtors (already in debit_account_id)
     *
     * The debit account for recovery comes from bank_account's linked COA.
     */
    protected function resolveCreditAccount(ClaimRegister $claim): int
    {
        // For CLAIM transactions: credit should go to Income account
        if ($claim->transaction_type === 'claim') {
            // Use Pending Claim Register Allowance (4230) as default income account for supplier claims
            $incomeAccount = ChartOfAccount::where('account_code', '4230')->first();
            if ($incomeAccount) {
                return $incomeAccount->id;
            }

            // Fallback to Indirect Income parent (4200)
            $indirectIncome = ChartOfAccount::where('account_code', '4200')->first();
            if ($indirectIncome) {
                return $indirectIncome->id;
            }
        }

        // For RECOVERY transactions: use bank account for debit side
        if (
            $claim->transaction_type === 'recovery' &&
            in_array($claim->payment_method, ['bank_transfer', 'cheque']) &&
            $claim->bank_account_id
        ) {
            $claim->loadMissing('bankAccount');
            $bankAccount = $claim->bankAccount;

            if ($bankAccount && $bankAccount->chart_of_account_id) {
                return $bankAccount->chart_of_account_id;
            }
        }

        // For cash recovery payments
        if ($claim->transaction_type === 'recovery' && $claim->payment_method === 'cash') {
            $cashAccount = ChartOfAccount::where('account_code', '1121')->first();
            if ($cashAccount) {
                return $cashAccount->id;
            }
        }

        // Fallback to preset credit_account_id
        return $claim->credit_account_id;
    }

    /**
     * Create a journal entry for a claim register record.
     * Claim: DR 1112 Pending Claims Debtors, CR Income
     * Recovery: DR Bank (HBL Main), CR 1112 Pending Claims Debtors
     *
     * @return JournalEntry|null
     */
    protected function createClaimJournalEntry(ClaimRegister $claim, int $creditAccountId)
    {
        $claim->loadMissing(['debitAccount', 'supplier']);

        // Use the transaction's debit or credit amount
        $amount = max((float) $claim->debit, (float) $claim->credit);

        if ($amount <= 0) {
            Log::info("Skipping JE for claim #{$claim->id}: amount is zero");

            return null;
        }

        $supplierName = $claim->supplier?->supplier_name ?? 'Unknown';
        $lines = [];

        if ($claim->transaction_type === 'claim') {
            // Claim raised: DR Pending Claims Debtors, CR Income/Adjustment
            $lines[] = [
                'account_id' => $claim->debit_account_id,
                'debit' => $amount,
                'credit' => 0,
                'description' => "Claim raised against {$supplierName}".($claim->claim_month ? " ({$claim->claim_month})" : ''),
                'cost_center_id' => 1,
            ];
            $lines[] = [
                'account_id' => $creditAccountId,
                'debit' => 0,
                'credit' => $amount,
                'description' => "Claim raised against {$supplierName}".($claim->claim_month ? " ({$claim->claim_month})" : ''),
                'cost_center_id' => 1,
            ];
        } else {
            // Recovery received: DR Bank, CR Pending Claims Debtors
            $lines[] = [
                'account_id' => $creditAccountId,
                'debit' => $amount,
                'credit' => 0,
                'description' => "Recovery from {$supplierName} via bank transfer".($claim->claim_month ? " ({$claim->claim_month})" : ''),
                'cost_center_id' => 1,
            ];
            $lines[] = [
                'account_id' => $claim->debit_account_id,
                'debit' => 0,
                'credit' => $amount,
                'description' => "Recovery from {$supplierName}".($claim->claim_month ? " ({$claim->claim_month})" : ''),
                'cost_center_id' => 1,
            ];
        }

        $description = "Claim Register - {$supplierName}";
        if ($claim->reference_number) {
            $description .= " ({$claim->reference_number})";
        }
        if ($claim->description) {
            $description .= " - {$claim->description}";
        }

        $journalEntryData = [
            'entry_date' => Carbon::parse($claim->transaction_date)->toDateString(),
            'reference' => $claim->reference_number,
            'description' => $description,
            'reference_type' => 'App\Models\ClaimRegister',
            'reference_id' => $claim->id,
            'lines' => $lines,
            'auto_post' => true,
        ];

        $result = $this->accountingService->createJournalEntry($journalEntryData);

        if ($result['success']) {
            Log::info("Journal entry created for claim #{$claim->id}: JE #{$result['data']->id}");

            return $result['data'];
        }

        Log::error("Failed to create JE for claim #{$claim->id}: ".$result['message']);

        return null;
    }

    /**
     * Recoveries posted before claims were settled through the Supplier Ledger Register each booked the money into
     * HBL Main as if it had come into the bank, while the ledger register's claim adjustment took the same claim off
     * again. Their journals are reversed on their own date; the claim register and the ledger register (and so every
     * closing balance) stay as they are. Running it again reverses nothing twice.
     *
     * @return array{recoveries: int, amount: float}
     */
    public function settleRecoveriesThroughLedger(): array
    {
        $result = ['recoveries' => 0, 'amount' => 0.0];

        // Posted claim rows are immutable in the database, so only their journals are reversed; the rows stay as entered.
        $recoveries = ClaimRegister::query()
            ->where('transaction_type', 'recovery')
            ->whereNotNull('journal_entry_id')
            ->orderBy('transaction_date')->orderBy('id')
            ->get();

        foreach ($recoveries as $recovery) {
            DB::transaction(function () use ($recovery, &$result) {
                $journalId = $recovery->journal_entry_id;

                if ($journalId && JournalEntry::whereKey($journalId)->where('status', 'posted')->exists()
                    && ! JournalEntry::where('reverses_entry_id', $journalId)->exists()) {
                    $reversal = $this->accountingService->reverseJournalEntry(
                        (int) $journalId,
                        "REVERSAL: claim recovery {$recovery->reference_number} is settled through the Supplier Ledger Register claim adjustment, not received in the bank",
                        Carbon::parse($recovery->transaction_date)->toDateString(),
                    );

                    if (! $reversal['success']) {
                        throw new \RuntimeException($reversal['message']);
                    }

                    $result['amount'] += (float) $recovery->credit;
                    $result['recoveries']++;
                }
            });
        }

        $result['amount'] = round($result['amount'], 2);

        return $result;
    }

    /**
     * Turns the single amount into debit/credit and fills the default accounts, exactly as the claim
     * register screens do before saving.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function withDefaultAccounts(array $data): array
    {
        // Convert amount input to debit/credit based on transaction_type
        if (isset($data['amount'])) {
            $amount = (float) $data['amount'];
            $transactionType = $data['transaction_type'] ?? 'claim';

            if ($transactionType === 'claim') {
                $data['debit'] = $amount;
                $data['credit'] = 0;
            } else {
                // recovery
                $data['debit'] = 0;
                $data['credit'] = $amount;
            }

            // Remove amount field as it's not stored in DB
            unset($data['amount']);
        }

        // Set debit account to 1112 (Pending Claims Debtors)
        $debtorsAccount = ChartOfAccount::where('account_code', '1112')->first();
        if ($debtorsAccount) {
            $data['debit_account_id'] = $debtorsAccount->id;
        }

        // Set credit account to account code 1171 (HBL Main Bank)
        $bankAccount = ChartOfAccount::where('account_code', '1171')->first();
        if ($bankAccount) {
            $data['credit_account_id'] = $bankAccount->id;

            // Also set bank_account_id if BankAccount record exists
            $hblBank = BankAccount::where('chart_of_account_id', $bankAccount->id)->first();
            if ($hblBank) {
                $data['bank_account_id'] = $hblBank->id;
            }
        }

        // A recovery is the claim adjusted in the supplier's ledger; the Supplier Ledger Register books it.
        $data['payment_method'] = ($data['transaction_type'] ?? 'claim') === 'recovery' ? ClaimRegister::PAYMENT_LEDGER_ADJUSTMENT : 'bank_transfer';

        return $data;
    }
}
