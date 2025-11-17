<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds budget management functionality for budget vs actual variance analysis.
     * Supports annual and periodic budgets with cost center breakdown.
     */
    public function up(): void
    {
        // Budget headers
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->string('budget_name');
            $table->text('description')->nullable();

            // Fiscal year
            $table->integer('fiscal_year');

            // Budget period
            $table->enum('budget_type', ['annual', 'quarterly', 'monthly'])
                ->default('annual');

            $table->date('start_date');
            $table->date('end_date');

            // Status
            $table->enum('status', ['draft', 'approved', 'active', 'closed'])
                ->default('draft');

            // Approval tracking
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('fiscal_year');
            $table->index('status');
            $table->index(['fiscal_year', 'budget_type']);
        });

        // Budget line items (by account and optionally cost center)
        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')
                ->constrained('budgets')
                ->cascadeOnDelete();

            $table->foreignId('chart_of_account_id')
                ->constrained('chart_of_accounts')
                ->restrictOnDelete();

            $table->foreignId('cost_center_id')
                ->nullable()
                ->constrained('cost_centers')
                ->restrictOnDelete();

            // Budget amounts by period
            $table->decimal('january', 15, 2)->default(0);
            $table->decimal('february', 15, 2)->default(0);
            $table->decimal('march', 15, 2)->default(0);
            $table->decimal('april', 15, 2)->default(0);
            $table->decimal('may', 15, 2)->default(0);
            $table->decimal('june', 15, 2)->default(0);
            $table->decimal('july', 15, 2)->default(0);
            $table->decimal('august', 15, 2)->default(0);
            $table->decimal('september', 15, 2)->default(0);
            $table->decimal('october', 15, 2)->default(0);
            $table->decimal('november', 15, 2)->default(0);
            $table->decimal('december', 15, 2)->default(0);

            // Total annual budget
            $table->decimal('total_annual', 15, 2)->default(0);

            // Notes for this line
            $table->text('notes')->nullable();

            $table->timestamps();

            // Prevent duplicate account+cost_center combinations per budget
            $table->unique(
                ['budget_id', 'chart_of_account_id', 'cost_center_id'],
                'unique_budget_account_cc'
            );

            // Indexes
            $table->index('chart_of_account_id');
            $table->index('cost_center_id');
        });

        // Budget variance tracking (calculated periodically)
        Schema::create('budget_variances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_line_id')
                ->constrained('budget_lines')
                ->cascadeOnDelete();

            $table->foreignId('accounting_period_id')
                ->constrained('accounting_periods')
                ->restrictOnDelete();

            $table->integer('month'); // 1-12
            $table->integer('year');

            // Amounts
            $table->decimal('budget_amount', 15, 2);
            $table->decimal('actual_amount', 15, 2);
            $table->decimal('variance_amount', 15, 2); // actual - budget
            $table->decimal('variance_percentage', 8, 2); // (variance / budget) * 100

            // Variance type
            $table->enum('variance_type', ['favorable', 'unfavorable', 'on_target'])
                ->nullable();

            // Calculation timestamp
            $table->timestamp('calculated_at');
            $table->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Unique constraint
            $table->unique(
                ['budget_line_id', 'accounting_period_id'],
                'unique_variance_period'
            );

            // Indexes
            $table->index(['year', 'month']);
            $table->index('variance_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budget_variances');
        Schema::dropIfExists('budget_lines');
        Schema::dropIfExists('budgets');
    }
};
