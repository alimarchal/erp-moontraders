<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
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
            $table->foreignId('supplier_id')->nullable()->constrained()->onDelete('restrict');

            // Opening Stock
            $table->decimal('total_quantity_issued', 15, 3)->default(0);
            $table->decimal('total_value_issued', 15, 2)->default(0);

            // Sales Summary
            $table->decimal('total_sales_amount', 15, 2)->default(0);
            $table->decimal('cash_sales_amount', 15, 2)->default(0);
            $table->decimal('bank_slips_amount', 15, 2)->default(0);
            $table->decimal('cheque_sales_amount', 15, 2)->default(0);
            $table->decimal('bank_transfer_amount', 15, 2)->default(0);
            $table->decimal('credit_sales_amount', 15, 2)->default(0);

            // Stock Summary
            $table->decimal('total_quantity_sold', 15, 3)->default(0);
            $table->decimal('total_quantity_returned', 15, 3)->default(0);
            $table->decimal('total_quantity_shortage', 15, 3)->default(0);

            // Cash Reconciliation
            $table->decimal('cash_collected', 15, 2)->default(0);
            $table->decimal('cheques_collected', 15, 2)->default(0);
            $table->decimal('expenses_claimed', 15, 2)->default(0);
            $table->decimal('gross_profit', 15, 2)->nullable()->comment('Calculated: total_sales_amount - total_cogs');
            $table->decimal('total_cogs', 15, 2)->nullable()->comment('Total cost of goods sold');
            $table->decimal('cash_to_deposit', 15, 2)->default(0);

            // Credit Recoveries
            $table->decimal('credit_recoveries', 15, 2)->default(0);

            // Note: Individual expense fields removed - now stored in sales_settlement_expenses table
            // This provides scalability for unlimited expense types without schema changes

            $table->enum('status', ['draft', 'verified', 'posted'])->default('draft');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->userTracking();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['settlement_date', 'status']);
            $table->index('employee_id');
            $table->index('vehicle_id');
        });

        // Create sales settlement cash denominations table
        Schema::create('sales_settlement_cash_denominations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_settlement_id')->constrained('sales_settlements')->cascadeOnDelete();
            $table->integer('denom_5000')->default(0);
            $table->integer('denom_1000')->default(0);
            $table->integer('denom_500')->default(0);
            $table->integer('denom_100')->default(0);
            $table->integer('denom_50')->default(0);
            $table->integer('denom_20')->default(0);
            $table->integer('denom_10')->default(0);
            $table->decimal('denom_coins', 10, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->userTracking();
            $table->timestamps();

            $table->index('sales_settlement_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_settlement_cash_denominations');
        Schema::dropIfExists('sales_settlements');
    }
};
