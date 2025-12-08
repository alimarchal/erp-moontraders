<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sales_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('settlement_number')->unique();
            $table->date('settlement_date');
            $table->foreignId('goods_issue_id')->constrained('goods_issues')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();

            // Opening Stock
            $table->decimal('total_quantity_issued', 15, 3)->default(0);
            $table->decimal('total_value_issued', 15, 2)->default(0);

            // Sales Summary
            $table->decimal('total_sales_amount', 15, 2)->default(0);
            $table->decimal('cash_sales_amount', 15, 2)->default(0);
            $table->decimal('cheque_sales_amount', 15, 2)->default(0);
            $table->decimal('credit_sales_amount', 15, 2)->default(0);

            // Stock Summary
            $table->decimal('total_quantity_sold', 15, 3)->default(0);
            $table->decimal('total_quantity_returned', 15, 3)->default(0);
            $table->decimal('total_quantity_shortage', 15, 3)->default(0);

            // Cash Reconciliation
            $table->decimal('cash_collected', 15, 2)->default(0);
            $table->decimal('cheques_collected', 15, 2)->default(0);
            $table->decimal('expenses_claimed', 15, 2)->default(0);
            $table->decimal('cash_to_deposit', 15, 2)->default(0);

            // Credit Recoveries
            $table->decimal('credit_recoveries', 15, 2)->default(0);

            // Detailed Expense Fields
            $table->decimal('expense_toll_tax', 12, 2)->default(0);
            $table->decimal('expense_amr_powder_claim', 12, 2)->default(0);
            $table->decimal('expense_amr_liquid_claim', 12, 2)->default(0);
            $table->decimal('expense_scheme', 12, 2)->default(0);
            $table->decimal('expense_advance_tax', 12, 2)->default(0);
            $table->decimal('expense_food_charges', 12, 2)->default(0);
            $table->decimal('expense_salesman_charges', 12, 2)->default(0);
            $table->decimal('expense_loader_charges', 12, 2)->default(0);
            $table->decimal('expense_percentage', 12, 2)->default(0);
            $table->decimal('expense_miscellaneous_amount', 12, 2)->default(0);

            $table->enum('status', ['draft', 'verified', 'posted'])->default('draft');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['settlement_date', 'status']);
            $table->index('employee_id');
            $table->index('vehicle_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_settlements');
    }
};
