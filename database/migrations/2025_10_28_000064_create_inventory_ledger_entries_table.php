<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_ledger_entries', function (Blueprint $table) {
            $table->id();

            // Transaction basics
            $table->date('date')->index();
            $table->string('transaction_type')->index();

            // Relationships
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('stock_batch_id')->nullable()->constrained('stock_batches')->comment('Batch for traceability');
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses');
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles');
            $table->foreignId('employee_id')->nullable()->constrained('employees');

            // Double-entry quantities (always positive, 2 decimals)
            $table->decimal('debit_qty', 15, 2)->default(0)->comment('Stock IN (always positive)');
            $table->decimal('credit_qty', 15, 2)->default(0)->comment('Stock OUT (always positive)');

            // Values (2 decimals)
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('selling_price', 15, 2)->default(0);
            $table->decimal('total_value', 15, 2)->default(0);

            // Running balance for quick lookups
            $table->decimal('running_balance', 15, 2)->default(0);

            // Reference documents (only one should be set per entry)
            $table->foreignId('goods_receipt_note_id')->nullable()->constrained('goods_receipt_notes');
            $table->foreignId('goods_issue_id')->nullable()->constrained('goods_issues');
            $table->foreignId('sales_settlement_id')->nullable()->constrained('sales_settlements');

            // Notes
            $table->text('notes')->nullable();

            // Audit trail (UserTracking trait)
            $table->userTracking();

            $table->timestamps();

            // Composite indexes for common report queries
            $table->index(['date', 'product_id']);
            $table->index(['product_id', 'warehouse_id']);
            $table->index(['product_id', 'vehicle_id']);
            $table->index(['stock_batch_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_ledger_entries');
    }
};
