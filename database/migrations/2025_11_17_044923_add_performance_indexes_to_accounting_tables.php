<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds performance indexes to improve query speed for:
     * - Journal entry lookups by date and status
     * - Account balance calculations
     * - General ledger queries
     * - Period-based reporting
     */
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            // Index for date-based queries (reports, period filtering)
            $table->index('entry_date', 'idx_je_entry_date');

            // Index for status queries (posted vs draft)
            $table->index('status', 'idx_je_status');

            // Composite index for common query pattern (status + date)
            $table->index(['status', 'entry_date'], 'idx_je_status_date');

            // Index for period-based queries
            $table->index('accounting_period_id', 'idx_je_period_id');

            // Index for finding reversing entries
            $table->index('reverses_entry_id', 'idx_je_reverses');
            $table->index('reversed_by_entry_id', 'idx_je_reversed_by');
        });

        Schema::table('journal_entry_details', function (Blueprint $table) {
            // Composite index for account-based queries (most common)
            // This speeds up account balance calculations significantly
            $table->index(['chart_of_account_id', 'journal_entry_id'], 'idx_jed_account_entry');

            // Index for cost center reporting
            $table->index('cost_center_id', 'idx_jed_cost_center');
        });

        Schema::table('chart_of_accounts', function (Blueprint $table) {
            // Index for account code lookups (used in imports/integrations)
            $table->index('account_code', 'idx_coa_code');

            // Index for account type queries (balance sheet, income statement)
            $table->index('account_type_id', 'idx_coa_type');

            // Index for active account queries
            $table->index('is_active', 'idx_coa_active');

            // Index for hierarchical queries (parent-child relationships)
            $table->index('parent_id', 'idx_coa_parent');

            // Composite index for common query pattern (type + active)
            $table->index(['account_type_id', 'is_active'], 'idx_coa_type_active');
        });

        Schema::table('accounting_periods', function (Blueprint $table) {
            // Index for status queries (open periods)
            $table->index('status', 'idx_ap_status');

            // Composite index for date range queries
            $table->index(['start_date', 'end_date'], 'idx_ap_date_range');
        });

        Schema::table('account_types', function (Blueprint $table) {
            // Index for report group queries (balance sheet vs income statement)
            $table->index('report_group', 'idx_at_report_group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex('idx_je_entry_date');
            $table->dropIndex('idx_je_status');
            $table->dropIndex('idx_je_status_date');
            $table->dropIndex('idx_je_period_id');
            $table->dropIndex('idx_je_reverses');
            $table->dropIndex('idx_je_reversed_by');
        });

        Schema::table('journal_entry_details', function (Blueprint $table) {
            $table->dropIndex('idx_jed_account_entry');
            $table->dropIndex('idx_jed_cost_center');
        });

        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropIndex('idx_coa_code');
            $table->dropIndex('idx_coa_type');
            $table->dropIndex('idx_coa_active');
            $table->dropIndex('idx_coa_parent');
            $table->dropIndex('idx_coa_type_active');
        });

        Schema::table('accounting_periods', function (Blueprint $table) {
            $table->dropIndex('idx_ap_status');
            $table->dropIndex('idx_ap_date_range');
        });

        Schema::table('account_types', function (Blueprint $table) {
            $table->dropIndex('idx_at_report_group');
        });
    }
};
