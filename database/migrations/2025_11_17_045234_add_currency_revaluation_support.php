<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds currency revaluation tracking for IAS 21 compliance.
     * Tracks exchange rate changes and unrealized gains/losses on foreign currency balances.
     */
    public function up(): void
    {
        // Historical exchange rates
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('currency_id')
                ->constrained('currencies')
                ->cascadeOnDelete();

            $table->date('effective_date');
            $table->decimal('rate_to_base', 15, 6); // Rate to convert to base currency
            $table->enum('rate_type', ['spot', 'average', 'month_end', 'year_end'])
                ->default('spot');

            $table->string('source', 100)->nullable(); // e.g., 'Central Bank', 'Bloomberg'

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Unique constraint: one rate per currency per date per type
            $table->unique(
                ['currency_id', 'effective_date', 'rate_type'],
                'unique_currency_date_type'
            );

            // Indexes
            $table->index('effective_date');
            $table->index(['currency_id', 'effective_date']);
        });

        // Currency revaluation runs (for period-end revaluations)
        Schema::create('currency_revaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_period_id')
                ->constrained('accounting_periods')
                ->restrictOnDelete();

            $table->date('revaluation_date');
            $table->text('description')->nullable();

            // Status
            $table->enum('status', ['calculated', 'posted', 'reversed'])
                ->default('calculated');

            // Total unrealized gain/loss
            $table->decimal('total_gain_loss', 15, 2)->default(0);

            // Link to journal entry
            $table->foreignId('journal_entry_id')
                ->nullable()
                ->constrained('journal_entries')
                ->nullOnDelete();

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('revaluation_date');
            $table->index('status');
        });

        // Currency revaluation details (per account)
        Schema::create('currency_revaluation_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('currency_revaluation_id')
                ->constrained('currency_revaluations')
                ->cascadeOnDelete();

            $table->foreignId('chart_of_account_id')
                ->constrained('chart_of_accounts')
                ->restrictOnDelete();

            $table->foreignId('currency_id')
                ->constrained('currencies')
                ->restrictOnDelete();

            // Foreign currency balance
            $table->decimal('fc_balance', 15, 2); // Balance in foreign currency

            // Exchange rates
            $table->decimal('old_rate', 15, 6); // Previous rate
            $table->decimal('new_rate', 15, 6); // Current rate

            // Base currency amounts
            $table->decimal('old_base_amount', 15, 2); // Previous base currency value
            $table->decimal('new_base_amount', 15, 2); // Current base currency value

            // Gain or loss
            $table->decimal('unrealized_gain_loss', 15, 2); // new - old
            $table->enum('gain_loss_type', ['gain', 'loss', 'none']);

            $table->timestamps();

            // Indexes
            $table->index('chart_of_account_id');
        });

        // Add unrealized gain/loss accounts to chart of accounts (via seeder would be better)
        // But add tracking to journal entries
        Schema::table('journal_entries', function (Blueprint $table) {
            // Mark entries as currency revaluation entries
            $table->boolean('is_revaluation_entry')->default(false)->after('is_closing_entry');

            // Index
            $table->index('is_revaluation_entry', 'idx_je_revaluation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex('idx_je_revaluation');
            $table->dropColumn('is_revaluation_entry');
        });

        Schema::dropIfExists('currency_revaluation_details');
        Schema::dropIfExists('currency_revaluations');
        Schema::dropIfExists('exchange_rates');
    }
};
