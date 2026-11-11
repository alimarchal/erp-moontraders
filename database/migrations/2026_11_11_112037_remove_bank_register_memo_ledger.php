<?php

use App\Models\BankEntryCategory;
use App\Models\SalesSettlement;
use App\Services\BankRegister\BankRegisterAccounts;
use App\Services\BankRegister\MemoLedgerRemoval;
use App\Services\BankRegister\SettlementBankRegisterSync;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Bank Register stops keeping its own memo books (account type "Bank Register", accounts 6000-6999) and posts
     * to the live chart of accounts instead:
     *
     * 1. Its journals, accounts and account type are deleted for good (MemoLedgerRemoval checks that none of those
     *    journals touches a live account first, so no live balance changes). Lines that were posted there go back to
     *    "Ready to post" with their receipt numbers.
     * 2. bank_accounts.register_chart_of_account_id goes; the bank side is the bank account's own ledger.
     * 3. The few accounts the register needs in the live chart are created (Bank Charges, Advance Tax - Bank
     *    Deductions, Bank Transfers in Transit, Bank Suspense, Owner / Partner Current Account) and every category
     *    without an account gets the one its kind of money belongs to (editable in Settings › Bank categories).
     * 4. The register lines of posted settlements are linked to the settlement journals ("booked by settlement").
     *
     * Not reversible: the memo journals and accounts are gone.
     */
    public function up(): void
    {
        $removed = app(MemoLedgerRemoval::class)->run();
        Log::info('Bank Register memo ledger removed', $removed);

        $this->restoreReportGroupCheck();

        if (Schema::hasColumn('bank_accounts', 'register_chart_of_account_id')) {
            Schema::table('bank_accounts', function (Blueprint $table) {
                $table->dropConstrainedForeignId('register_chart_of_account_id');
            });
        }

        if (! Schema::hasColumn('bank_statement_entries', 'not_in_books_reason')) {
            Schema::table('bank_statement_entries', function (Blueprint $table) {
                $table->string('not_in_books_reason', 500)->nullable()->after('rejection_reason')
                    ->comment('Why this line is posted although the bank ledger has a line of the same amount within 3 days.');
            });
        }

        $accounts = app(BankRegisterAccounts::class);

        if ($accounts->ensureAll()) {
            foreach (BankEntryCategory::query()->whereNull('default_chart_of_account_id')->get() as $category) {
                $key = BankRegisterAccounts::CATEGORY_ACCOUNTS[$category->slug] ?? null;

                if ($key) {
                    $category->forceFill(['default_chart_of_account_id' => $accounts->account($key)->id])->save();
                }
            }
        }

        $sync = app(SettlementBankRegisterSync::class);

        SalesSettlement::query()
            ->whereIn('id', DB::table('bank_statement_entries')->whereNotNull('sales_settlement_id')->distinct()->pluck('sales_settlement_id'))
            ->get()
            ->each(fn (SalesSettlement $settlement) => DB::transaction(fn () => $sync->sync($settlement)));
    }

    /**
     * account_types.report_group was widened for the memo ledger; with its type gone it goes back to the two groups.
     */
    private function restoreReportGroupCheck(): void
    {
        if (DB::table('account_types')->whereNotIn('report_group', ['BalanceSheet', 'IncomeStatement'])->exists()) {
            return;
        }

        try {
            DB::statement('ALTER TABLE account_types DROP CONSTRAINT chk_report_group');
        } catch (Throwable) {
            // not there
        }

        try {
            DB::statement("ALTER TABLE account_types ADD CONSTRAINT chk_report_group CHECK (report_group IN ('BalanceSheet', 'IncomeStatement'))");
        } catch (Throwable $e) {
            Log::warning('Could not restore chk_report_group: '.$e->getMessage());
        }
    }

    public function down(): void
    {
        throw new RuntimeException('The Bank Register memo ledger was deleted for good; restore a backup to go back.');
    }
};
