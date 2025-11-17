<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds depreciation tracking for fixed assets.
     * Supports straight-line, declining balance, and units of production methods.
     */
    public function up(): void
    {
        // Fixed Assets register
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code', 50)->unique();
            $table->string('asset_name');
            $table->text('description')->nullable();

            // Account links
            $table->foreignId('asset_account_id')
                ->constrained('chart_of_accounts')
                ->restrictOnDelete();
            $table->foreignId('accumulated_depreciation_account_id')
                ->constrained('chart_of_accounts')
                ->restrictOnDelete();
            $table->foreignId('depreciation_expense_account_id')
                ->constrained('chart_of_accounts')
                ->restrictOnDelete();

            // Cost centers (optional)
            $table->foreignId('cost_center_id')
                ->nullable()
                ->constrained('cost_centers')
                ->nullOnDelete();

            // Financial details
            $table->decimal('cost', 15, 2);
            $table->decimal('salvage_value', 15, 2)->default(0);
            $table->decimal('depreciable_amount', 15, 2); // cost - salvage

            // Depreciation settings
            $table->enum('depreciation_method', [
                'straight_line',
                'declining_balance',
                'double_declining_balance',
                'units_of_production',
            ])->default('straight_line');

            $table->integer('useful_life_years')->nullable(); // For time-based methods
            $table->integer('useful_life_months')->nullable(); // More precise
            $table->decimal('useful_life_units', 15, 2)->nullable(); // For units of production

            // Dates
            $table->date('acquisition_date');
            $table->date('depreciation_start_date');
            $table->date('disposal_date')->nullable();

            // Status
            $table->enum('status', ['active', 'fully_depreciated', 'disposed', 'under_construction'])
                ->default('active');

            // Disposal information
            $table->decimal('disposal_proceeds', 15, 2)->nullable();
            $table->foreignId('disposal_journal_entry_id')
                ->nullable()
                ->constrained('journal_entries')
                ->nullOnDelete();

            // Tracking
            $table->decimal('total_depreciation', 15, 2)->default(0);
            $table->decimal('book_value', 15, 2); // cost - total_depreciation

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Indexes (asset_code already has unique index from line 20)
            $table->index('status');
            $table->index('acquisition_date');
            $table->index('depreciation_start_date');
        });

        // Depreciation schedule/history
        Schema::create('depreciation_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')
                ->constrained('fixed_assets')
                ->cascadeOnDelete();

            $table->foreignId('accounting_period_id')
                ->constrained('accounting_periods')
                ->restrictOnDelete();

            $table->date('depreciation_date');
            $table->decimal('depreciation_amount', 15, 2);
            $table->decimal('accumulated_depreciation', 15, 2);
            $table->decimal('book_value_after', 15, 2);

            // Link to journal entry
            $table->foreignId('journal_entry_id')
                ->nullable()
                ->constrained('journal_entries')
                ->nullOnDelete();

            // Status
            $table->enum('status', ['calculated', 'posted', 'reversed'])
                ->default('calculated');

            // For units of production
            $table->decimal('units_produced', 15, 2)->nullable();

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('depreciation_date');
            $table->index('status');
            $table->unique(['fixed_asset_id', 'accounting_period_id'], 'unique_asset_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('depreciation_entries');
        Schema::dropIfExists('fixed_assets');
    }
};
