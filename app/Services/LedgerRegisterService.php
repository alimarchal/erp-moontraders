<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\LedgerRegister;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LedgerRegisterService
{
    public function __construct(private AccountingService $accountingService) {}

    /**
     * Post a ledger register entry to the GL.
     *
     * Double-entry per column:
     *   invoice_amount  → DR 1151 Stock In Hand         / CR 2111 Creditors
     *   online_amount   → DR 2111 Creditors             / CR 1171 HBL Main Account
     *   expenses_amount → DR 5210 Admin Expenses        / CR 2111 Creditors
     *   za_amount       → DR 2111 Creditors             / CR 4240 ZA 0.5% Incentive Income
     *   claim_adjust    → DR 2111 Creditors             / CR 1112 Pending Claims Debtors
     *
     * @return array{success: bool, data: ?LedgerRegister, message: string}
     */
    public function postEntry(LedgerRegister $entry): array
    {
        try {
            DB::beginTransaction();

            if ($entry->isPosted()) {
                throw new \Exception('Entry is already posted.');
            }

            $accounts = $this->resolveAccounts();
            $journalEntry = $this->createJournalEntry($entry, $accounts);

            if (! $journalEntry) {
                throw new \Exception('Failed to create journal entry — check GL account configuration.');
            }

            $entry->update([
                'posted_at' => now(),
                'posted_by' => auth()->id(),
                'journal_entry_id' => $journalEntry->id,
            ]);

            DB::commit();

            return [
                'success' => true,
                'data' => $entry->fresh(),
                'message' => "Ledger entry posted successfully with journal entry #{$journalEntry->id}.",
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to post ledger register entry', [
                'entry_id' => $entry->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to post entry: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Resolve all required GL account IDs by account code.
     *
     * @return array<string, int>
     */
    protected function resolveAccounts(): array
    {
        $codes = ['2111', '1151', '1171', '5210', '4240', '1112'];
        $accounts = ChartOfAccount::whereIn('account_code', $codes)->get()->keyBy('account_code');

        $missing = array_filter($codes, fn ($code) => ! $accounts->has($code));
        if (! empty($missing)) {
            throw new \Exception('Missing GL accounts: '.implode(', ', $missing));
        }

        return [
            'creditors' => $accounts['2111']->id,
            'stock_in_hand' => $accounts['1151']->id,
            'hbl_main' => $accounts['1171']->id,
            'admin_expenses' => $accounts['5210']->id,
            'za_incentive_income' => $accounts['4240']->id,
            'pending_claims_debtors' => $accounts['1112']->id,
        ];
    }

    /**
     * Build and create the journal entry lines for a ledger register entry.
     *
     * @param  array<string, int>  $accounts
     * @return JournalEntry|null
     */
    protected function createJournalEntry(LedgerRegister $entry, array $accounts)
    {
        $entry->loadMissing('supplier');
        $supplierName = $entry->supplier?->supplier_name ?? 'Unknown';
        $docRef = $entry->document_number ?? "LR-{$entry->id}";
        $lines = [];

        // invoice_amount: DR Stock In Hand / CR Creditors
        if ((float) $entry->invoice_amount > 0) {
            $lines[] = ['account_id' => $accounts['stock_in_hand'], 'debit' => (float) $entry->invoice_amount, 'credit' => 0, 'description' => "Stock purchase from {$supplierName}", 'cost_center_id' => 1];
            $lines[] = ['account_id' => $accounts['creditors'], 'debit' => 0, 'credit' => (float) $entry->invoice_amount, 'description' => "Stock purchase from {$supplierName}", 'cost_center_id' => 1];
        }

        // online_amount: DR Creditors / CR HBL Main
        if ((float) $entry->online_amount > 0) {
            $lines[] = ['account_id' => $accounts['creditors'], 'debit' => (float) $entry->online_amount, 'credit' => 0, 'description' => "Payment to {$supplierName} via bank", 'cost_center_id' => 1];
            $lines[] = ['account_id' => $accounts['hbl_main'], 'debit' => 0, 'credit' => (float) $entry->online_amount, 'description' => "Payment to {$supplierName} via bank", 'cost_center_id' => 1];
        }

        // expenses_amount: DR Admin Expenses / CR Creditors
        if ((float) $entry->expenses_amount > 0) {
            $lines[] = ['account_id' => $accounts['admin_expenses'], 'debit' => (float) $entry->expenses_amount, 'credit' => 0, 'description' => "Expenses charged by {$supplierName}", 'cost_center_id' => 1];
            $lines[] = ['account_id' => $accounts['creditors'], 'debit' => 0, 'credit' => (float) $entry->expenses_amount, 'description' => "Expenses charged by {$supplierName}", 'cost_center_id' => 1];
        }

        // za_point_five_percent_amount: DR Creditors / CR ZA 0.5% Incentive Income
        if ((float) $entry->za_point_five_percent_amount > 0) {
            $lines[] = ['account_id' => $accounts['creditors'], 'debit' => (float) $entry->za_point_five_percent_amount, 'credit' => 0, 'description' => "ZA 0.5% incentive from {$supplierName}", 'cost_center_id' => 1];
            $lines[] = ['account_id' => $accounts['za_incentive_income'], 'debit' => 0, 'credit' => (float) $entry->za_point_five_percent_amount, 'description' => "ZA 0.5% incentive from {$supplierName}", 'cost_center_id' => 1];
        }

        // claim_adjust_amount: DR Creditors / CR Pending Claims Debtors (or reverse if negative)
        if ((float) $entry->claim_adjust_amount != 0) {
            $absAmount = abs((float) $entry->claim_adjust_amount);
            if ((float) $entry->claim_adjust_amount > 0) {
                $lines[] = ['account_id' => $accounts['creditors'], 'debit' => $absAmount, 'credit' => 0, 'description' => "Claim adjustment from {$supplierName}", 'cost_center_id' => 1];
                $lines[] = ['account_id' => $accounts['pending_claims_debtors'], 'debit' => 0, 'credit' => $absAmount, 'description' => "Claim adjustment from {$supplierName}", 'cost_center_id' => 1];
            } else {
                $lines[] = ['account_id' => $accounts['pending_claims_debtors'], 'debit' => $absAmount, 'credit' => 0, 'description' => "Claim adjustment from {$supplierName}", 'cost_center_id' => 1];
                $lines[] = ['account_id' => $accounts['creditors'], 'debit' => 0, 'credit' => $absAmount, 'description' => "Claim adjustment from {$supplierName}", 'cost_center_id' => 1];
            }
        }

        if (empty($lines)) {
            throw new \Exception('No non-zero amounts found — nothing to post.');
        }

        $result = $this->accountingService->createJournalEntry([
            'entry_date' => Carbon::parse($entry->transaction_date)->toDateString(),
            'reference' => $docRef,
            'description' => "Ledger Register - {$supplierName} ({$docRef})",
            'reference_type' => LedgerRegister::class,
            'reference_id' => $entry->id,
            'lines' => $lines,
            'auto_post' => true,
        ]);

        if ($result['success']) {
            Log::info("Journal entry created for ledger register #{$entry->id}: JE #{$result['data']->id}");

            return $result['data'];
        }

        Log::error("Failed to create JE for ledger register #{$entry->id}: ".$result['message']);

        return null;
    }
}
