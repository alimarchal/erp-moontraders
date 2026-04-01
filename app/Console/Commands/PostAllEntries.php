<?php

namespace App\Console\Commands;

use App\Models\ChartOfAccount;
use App\Models\ClaimRegister;
use App\Models\CustomerEmployeeAccountTransaction;
use App\Models\ExpenseDetail;
use App\Models\GoodsReceiptNote;
use App\Models\LedgerRegister;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\ClaimRegisterService;
use App\Services\ExpenseDetailService;
use App\Services\InventoryService;
use App\Services\LedgerRegisterService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

#[Signature('app:post-all-entries')]
#[Description('Fix all unposted entry dates to 2026-03-31 17:00:00 and post them')]
class PostAllEntries extends Command
{
    public function handle(
        LedgerRegisterService $ledgerService,
        ClaimRegisterService $claimService,
        ExpenseDetailService $expenseService,
        InventoryService $inventoryService,
        AccountingService $accountingService,
    ): void {
        Auth::login(User::find(1));

        $targetDate = '2026-03-31';
        $targetTimestamp = '2026-03-31 17:00:00';

        // --- Fix dates ---
        $this->info('Updating dates to 2026-03-31 17:00:00...');

        DB::table('supplier_ledger_registers')->whereNull('posted_at')->update([
            'transaction_date' => $targetDate,
            'created_at' => $targetTimestamp,
            'updated_at' => $targetTimestamp,
        ]);

        DB::table('claim_registers')->whereNull('posted_at')->update([
            'transaction_date' => $targetDate,
            'created_at' => $targetTimestamp,
            'updated_at' => $targetTimestamp,
        ]);

        DB::table('expense_details')->whereNull('posted_at')->update([
            'transaction_date' => $targetDate,
            'created_at' => $targetTimestamp,
            'updated_at' => $targetTimestamp,
        ]);

        DB::table('goods_receipt_notes')->where('status', 'draft')->update([
            'receipt_date' => $targetDate,
            'created_at' => $targetTimestamp,
            'updated_at' => $targetTimestamp,
        ]);

        DB::table('customer_employee_account_transactions')
            ->whereNull('posted_at')
            ->where('transaction_type', 'opening_balance')
            ->update([
                'transaction_date' => $targetDate,
                'created_at' => $targetTimestamp,
                'updated_at' => $targetTimestamp,
            ]);

        $this->line('All dates updated.');

        // --- Open Q1 2026 ---
        DB::table('accounting_periods')->where('id', 7)->update(['status' => 'open']);
        $this->info('Q1 2026 accounting period opened.');

        // --- Post GRN entries ---
        $grnEntries = GoodsReceiptNote::where('status', 'draft')->with('items')->get();
        $this->info("Posting {$grnEntries->count()} GRN entries...");
        foreach ($grnEntries as $grn) {
            $result = $inventoryService->postGrnToInventory($grn);
            $this->line('GRN #'.$grn->grn_number.': '.($result['success'] ? 'OK' : 'FAILED - '.$result['message']));
        }

        // --- Post Ledger entries ---
        $ledgerEntries = LedgerRegister::whereNull('posted_at')->get();
        $this->info("Posting {$ledgerEntries->count()} ledger entries...");
        foreach ($ledgerEntries as $entry) {
            $result = $ledgerService->postEntry($entry);
            $this->line('Ledger #'.$entry->id.': '.($result['success'] ? 'OK' : 'FAILED - '.$result['message']));
        }

        // --- Post Claim entries ---
        $claimEntries = ClaimRegister::whereNull('posted_at')->get();
        $this->info("Posting {$claimEntries->count()} claim entries...");
        foreach ($claimEntries as $claim) {
            $result = $claimService->postClaim($claim);
            $this->line('Claim #'.$claim->id.': '.($result['success'] ? 'OK' : 'FAILED - '.$result['message']));
        }

        // --- Post Expense Detail entries ---
        $expenseEntries = ExpenseDetail::whereNull('posted_at')->get();
        $this->info("Posting {$expenseEntries->count()} expense detail entries...");
        foreach ($expenseEntries as $expense) {
            $result = $expenseService->postExpense($expense);
            $this->line('Expense #'.$expense->id.': '.($result['success'] ? 'OK' : 'FAILED - '.$result['message']));
        }

        // --- Post Opening Customer Balance entries ---
        $debtorsAccount = ChartOfAccount::where('account_name', 'Debtors')->first();
        $openingEquityAccount = ChartOfAccount::where('account_name', 'Opening Balance Equity')->first();

        $ocbEntries = CustomerEmployeeAccountTransaction::whereNull('posted_at')
            ->where('transaction_type', 'opening_balance')
            ->where('debit', '>', 0)
            ->with(['account.customer', 'account.employee.supplier'])
            ->get();

        $this->info("Posting {$ocbEntries->count()} opening customer balance entries...");

        if (! $debtorsAccount || ! $openingEquityAccount) {
            $this->error('GL accounts "Debtors" or "Opening Balance Equity" not found — skipping OCB posting.');
        } else {
            foreach ($ocbEntries as $ocb) {
                DB::beginTransaction();
                try {
                    $customerName = $ocb->account->customer->customer_name ?? 'Unknown';
                    $employeeName = $ocb->account->employee->name ?? 'Unknown';
                    $reference = $ocb->reference_number ?? 'OCB-'.$ocb->id;
                    $amount = (float) $ocb->debit;

                    $result = $accountingService->createJournalEntry([
                        'entry_date' => $targetDate,
                        'reference' => $reference,
                        'description' => "Opening Customer Balance — {$customerName} (Salesman: {$employeeName})",
                        'lines' => [
                            ['line_no' => 1, 'account_id' => $debtorsAccount->id, 'debit' => $amount, 'credit' => 0, 'description' => "Opening balance receivable — {$customerName}", 'cost_center_id' => null],
                            ['line_no' => 2, 'account_id' => $openingEquityAccount->id, 'debit' => 0, 'credit' => $amount, 'description' => "Opening balance equity — {$customerName}", 'cost_center_id' => null],
                        ],
                        'auto_post' => true,
                    ]);

                    if ($result['success']) {
                        $ocb->update(['journal_entry_id' => $result['data']->id, 'posted_at' => now(), 'posted_by' => auth()->id()]);
                        DB::commit();
                        $this->line('OCB #'.$ocb->id.' ('.$customerName.'): OK');
                    } else {
                        DB::rollBack();
                        $this->line('OCB #'.$ocb->id.': FAILED - '.$result['message']);
                    }
                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->line('OCB #'.$ocb->id.': FAILED - '.$e->getMessage());
                }
            }
        }

        // --- Close Q1 2026 again ---
        DB::table('accounting_periods')->where('id', 7)->update(['status' => 'closed']);
        $this->info('Q1 2026 accounting period closed again.');

        $this->info('All done!');
    }
}
