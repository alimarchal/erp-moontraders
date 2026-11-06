<?php

use App\Services\BankRegister\BankRegisterLedger;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The Bank Register posts to its own accounts, never to the ledger the rest of the system uses for
     * a bank account (chart_of_account_id), so its journals cannot change GRN, settlement or any other
     * balance. This column points at the register's own ledger for the account.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('bank_accounts', 'register_chart_of_account_id')) {
            Schema::table('bank_accounts', function (Blueprint $table) {
                $table->foreignId('register_chart_of_account_id')->nullable()->after('chart_of_account_id')
                    ->constrained('chart_of_accounts')->nullOnDelete()
                    ->comment('Own ledger of the Bank Register for this account; the live ledger is chart_of_account_id.');
            });
        }

        $this->allowRegisterReportGroup();

        // On a database that already has a chart of accounts, create the register accounts now
        // (new unique codes, existing accounts untouched). On an empty one they are created on first use.
        app(BankRegisterLedger::class)->ensureAll();
    }

    /**
     * account_types.report_group is limited by a CHECK to BalanceSheet / IncomeStatement. The register's accounts
     * get their own group, so they stay out of both statements; the two existing values are unchanged.
     */
    private function allowRegisterReportGroup(): void
    {
        try {
            DB::statement('ALTER TABLE account_types DROP CONSTRAINT IF EXISTS chk_report_group');
            DB::statement("ALTER TABLE account_types ADD CONSTRAINT chk_report_group CHECK (report_group IN ('BalanceSheet', 'IncomeStatement', 'BankRegister'))");
        } catch (Throwable $e) {
            Log::warning('Could not widen chk_report_group: '.$e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('register_chart_of_account_id');
        });

        try {
            DB::statement('ALTER TABLE account_types DROP CONSTRAINT IF EXISTS chk_report_group');
            DB::statement("ALTER TABLE account_types ADD CONSTRAINT chk_report_group CHECK (report_group IN ('BalanceSheet', 'IncomeStatement'))");
        } catch (Throwable $e) {
            // register account types still exist: the wider constraint stays
            Log::warning('Could not restore chk_report_group: '.$e->getMessage());
        }
    }
};
