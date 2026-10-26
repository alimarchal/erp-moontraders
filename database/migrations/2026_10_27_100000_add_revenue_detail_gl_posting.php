<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('revenue_details', 'journal_entry_id')) {
            Schema::table('revenue_details', function (Blueprint $table) {
                $table->foreignId('journal_entry_id')->nullable()->after('posted_by')->constrained('journal_entries')->nullOnDelete();
            });
        }

        // The chart of accounts row is created atomically. The trigger is replaced afterwards:
        // MySQL commits implicitly on trigger DDL, so it cannot share the transaction (the step is idempotent).
        DB::transaction(fn () => $this->createOtherRevenueAccount());
        $this->replaceUpdateTrigger(allowJournalLink: true);
    }

    public function down(): void
    {
        $this->replaceUpdateTrigger(allowJournalLink: false);

        DB::transaction(function () {
            $accountId = DB::table('chart_of_accounts')->where('account_code', '4260')->value('id');

            if ($accountId && ! DB::table('journal_entry_details')->where('chart_of_account_id', $accountId)->exists()) {
                DB::table('chart_of_accounts')->where('id', $accountId)->delete();
            }
        });

        if (Schema::hasColumn('revenue_details', 'journal_entry_id')) {
            Schema::table('revenue_details', function (Blueprint $table) {
                $table->dropConstrainedForeignId('journal_entry_id');
            });
        }
    }

    /**
     * 4260 Other Revenue under 4200 Indirect Income: every revenue category posts here.
     * Only inserted when the prerequisite data exists (it does after seeding); tests seed their own accounts.
     */
    private function createOtherRevenueAccount(): void
    {
        if (DB::table('chart_of_accounts')->where('account_code', '4260')->exists()) {
            return;
        }

        $parent = DB::table('chart_of_accounts')->where('account_code', '4200')->first();
        $currency = DB::table('currencies')->where('is_base_currency', true)->first()
            ?? DB::table('currencies')->first();
        $accountType = DB::table('chart_of_accounts')
            ->whereIn('account_code', ['4250', '4240', '4230', '4220', '4210', '4110'])
            ->whereNotNull('account_type_id')
            ->value('account_type_id')
            ?? DB::table('account_types')->where('type_name', 'Income')->value('id');

        if (! $parent || ! $currency || ! $accountType) {
            return;
        }

        DB::table('chart_of_accounts')->insert([
            'parent_id' => $parent->id,
            'account_type_id' => $accountType,
            'currency_id' => $currency->id,
            'account_code' => '4260',
            'account_name' => 'Other Revenue',
            'normal_balance' => 'credit',
            'description' => 'Revenue Detail entries of every revenue category (claims, incentives, allowances from companies).',
            'is_group' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Posted revenue stays immutable, except that its missing journal entry can be linked once
     * (journal_entry_id NULL -> value, every other column unchanged).
     */
    private function replaceUpdateTrigger(bool $allowJournalLink): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            $link = $allowJournalLink
                ? "IF OLD.journal_entry_id IS NULL AND NEW.journal_entry_id IS NOT NULL
                       AND (to_jsonb(NEW) - 'journal_entry_id' - 'updated_at') = (to_jsonb(OLD) - 'journal_entry_id' - 'updated_at') THEN
                        RETURN NEW;
                    END IF;"
                : '';

            DB::unprepared("
                CREATE OR REPLACE FUNCTION fn_block_posted_revenue_updates()
                RETURNS TRIGGER AS $$
                BEGIN
                    IF OLD.posted_at IS NOT NULL THEN
                        {$link}
                        RAISE EXCEPTION 'Posted revenue details are immutable. Cannot update posted revenues.';
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;
            ");

            return;
        }

        $link = $allowJournalLink
            ? 'IF OLD.journal_entry_id IS NULL AND NEW.journal_entry_id IS NOT NULL
                   AND NEW.revenue_category_id <=> OLD.revenue_category_id AND NEW.supplier_id <=> OLD.supplier_id
                   AND NEW.transaction_date <=> OLD.transaction_date AND NEW.description <=> OLD.description
                   AND NEW.amount <=> OLD.amount AND NEW.notes <=> OLD.notes AND NEW.posted_at <=> OLD.posted_at
                   AND NEW.posted_by <=> OLD.posted_by AND NEW.deleted_at <=> OLD.deleted_at THEN
                    SET @revenue_link_only = 1;
                ELSE'
            : '';
        $end = $allowJournalLink ? 'END IF;' : '';

        DB::unprepared('DROP TRIGGER IF EXISTS trg_block_posted_revenue_updates');
        DB::unprepared("
            CREATE TRIGGER trg_block_posted_revenue_updates
            BEFORE UPDATE ON revenue_details
            FOR EACH ROW
            BEGIN
                IF OLD.posted_at IS NOT NULL THEN
                    {$link}
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Posted revenue details are immutable. Cannot update posted revenues.';
                    {$end}
                END IF;
            END
        ");
    }
};
