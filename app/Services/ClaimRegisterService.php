<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\ClaimRegister;
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
     *   DR debit_account (e.g. 1111 Debtors)   = debit amount
     *   CR credit_account (e.g. Income account) = debit amount
     *
     * Recovery Received (credit > 0):
     *   DR debit_account (e.g. Bank/Cash)       = credit amount
     *   CR credit_account (e.g. 1111 Debtors)   = credit amount
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
     * If payment method is bank_transfer and a bank_account is selected,
     * use the bank account's linked chart_of_account_id.
     * Otherwise fall back to the user-selected credit_account_id.
     */
    protected function resolveCreditAccount(ClaimRegister $claim): int
    {
        if (
            in_array($claim->payment_method, ['bank_transfer', 'cheque']) &&
            $claim->bank_account_id
        ) {
            $claim->loadMissing('bankAccount');
            $bankAccount = $claim->bankAccount;

            if ($bankAccount && $bankAccount->chart_of_account_id) {
                return $bankAccount->chart_of_account_id;
            }
        }

        // For cash payments, resolve to 1131 (Cash) if credit_account is a group
        if ($claim->payment_method === 'cash') {
            $cashAccount = ChartOfAccount::where('account_code', '1131')->first();
            if ($cashAccount) {
                return $cashAccount->id;
            }
        }

        return $claim->credit_account_id;
    }

    /**
     * Create a journal entry for a claim register record.
     * Claim: DR 1111 Debtors, CR Income
     * Recovery: DR Bank (HBL Main), CR 1111 Debtors
     *
     * @return \App\Models\JournalEntry|null
     */
    protected function createClaimJournalEntry(ClaimRegister $claim, int $creditAccountId)
    {
        $claim->loadMissing(['debitAccount', 'supplier']);

        $amount = (float) $claim->amount;

        if ($amount <= 0) {
            Log::info("Skipping JE for claim #{$claim->id}: amount is zero");

            return null;
        }

        $supplierName = $claim->supplier?->supplier_name ?? 'Unknown';
        $lines = [];

        if ($claim->transaction_type === 'claim') {
            // Claim raised: DR Debtors, CR Income/Adjustment
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
            // Recovery received: DR Bank, CR Debtors
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
}
