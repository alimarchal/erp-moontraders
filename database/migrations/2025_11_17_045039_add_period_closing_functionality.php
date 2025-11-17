<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds functionality to track period closing journal entries.
     * This supports automated year-end closing of income/expense accounts to retained earnings.
     */
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            // Mark entries that are period-closing entries
            $table->boolean('is_closing_entry')->default(false)->after('status');

            // Link to the period that this closing entry closes
            $table->foreignId('closes_period_id')
                ->nullable()
                ->after('is_closing_entry')
                ->constrained('accounting_periods')
                ->nullOnDelete();

            // Index for querying closing entries
            $table->index('is_closing_entry', 'idx_je_closing_entry');
        });

        Schema::table('accounting_periods', function (Blueprint $table) {
            // Date when the period was closed
            $table->timestamp('closed_at')->nullable()->after('status');

            // User who closed the period
            $table->foreignId('closed_by')
                ->nullable()
                ->after('closed_at')
                ->constrained('users')
                ->nullOnDelete();

            // Link to the closing journal entry
            $table->foreignId('closing_journal_entry_id')
                ->nullable()
                ->after('closed_by')
                ->constrained('journal_entries')
                ->nullOnDelete();

            // Balances at period close (for validation)
            $table->decimal('closing_total_debits', 17, 2)->nullable()->after('closing_journal_entry_id');
            $table->decimal('closing_total_credits', 17, 2)->nullable()->after('closing_total_debits');
            $table->decimal('closing_net_income', 17, 2)->nullable()->after('closing_total_credits');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex('idx_je_closing_entry');
            $table->dropForeign(['closes_period_id']);
            $table->dropColumn(['is_closing_entry', 'closes_period_id']);
        });

        Schema::table('accounting_periods', function (Blueprint $table) {
            $table->dropForeign(['closed_by']);
            $table->dropForeign(['closing_journal_entry_id']);
            $table->dropColumn([
                'closed_at',
                'closed_by',
                'closing_journal_entry_id',
                'closing_total_debits',
                'closing_total_credits',
                'closing_net_income',
            ]);
        });
    }
};
