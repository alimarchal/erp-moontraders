<?php

namespace App\Console\Commands;

use App\Models\ClaimRegister;
use App\Models\ExpenseDetail;
use App\Models\LedgerRegister;
use App\Models\User;
use App\Services\ClaimRegisterService;
use App\Services\ExpenseDetailService;
use App\Services\LedgerRegisterService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

#[Signature('app:post-and-fix-dates')]
#[Description('Fix dates to 2026-03-31 17:00 and post all unposted entries')]
class PostAndFixDates extends Command
{
    public function handle(LedgerRegisterService $ledgerService, ClaimRegisterService $claimService, ExpenseDetailService $expenseService): void
    {
        Auth::login(User::find(1));

        $targetDate = '2026-03-31';
        $targetTimestamp = '2026-03-31 17:00:00';

        // --- Fix dates ---
        $this->info('Updating dates to 2026-03-31 17:00:00...');

        DB::table('supplier_ledger_registers')
            ->whereNull('posted_at')
            ->update([
                'transaction_date' => $targetDate,
                'created_at' => $targetTimestamp,
                'updated_at' => $targetTimestamp,
            ]);
        $this->line('Ledger dates updated.');

        DB::table('claim_registers')
            ->whereNull('posted_at')
            ->update([
                'transaction_date' => $targetDate,
                'created_at' => $targetTimestamp,
                'updated_at' => $targetTimestamp,
            ]);
        $this->line('Claim dates updated.');

        DB::table('expense_details')
            ->whereNull('posted_at')
            ->update([
                'transaction_date' => $targetDate,
                'created_at' => $targetTimestamp,
                'updated_at' => $targetTimestamp,
            ]);
        $this->line('Expense Detail dates updated.');

        // --- Open Q1 2026 ---
        DB::table('accounting_periods')->where('id', 7)->update(['status' => 'open']);
        $this->info('Q1 2026 accounting period opened.');

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

        // --- Close Q1 2026 again ---
        DB::table('accounting_periods')->where('id', 7)->update(['status' => 'closed']);
        $this->info('Q1 2026 accounting period closed again.');

        $this->info('All done!');
    }
}
