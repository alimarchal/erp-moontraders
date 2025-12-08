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
        Schema::create('stock_valuation_layers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('stock_batch_id')->nullable()->constrained('stock_batches')->nullOnDelete()->comment('Optional: NULL if batch tracking disabled');

            // Movement Reference
            $table->foreignId('stock_movement_id')->constrained('stock_movements')->cascadeOnDelete();
            $table->foreignId('grn_item_id')->nullable()->constrained('goods_receipt_note_items')->nullOnDelete();

            // Layer Details
            $table->date('receipt_date')->index();
            $table->decimal('quantity_received', 15, 2);
            $table->decimal('quantity_remaining', 15, 2)->comment('Decreases on FIFO/LIFO issue');

            // Costing
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('total_value', 15, 2)->default(0);

            // Priority Info (for priority-based issuing)
            $table->integer('priority_order')->default(99)->index();
            $table->date('must_sell_before')->nullable()->index();
            $table->boolean('is_promotional')->default(false)->index();

            // Status
            $table->boolean('is_depleted')->default(false)->index();

            $table->timestamps();

            // Indexes
            $table->index(['product_id', 'warehouse_id']);
            $table->index('stock_batch_id');
            $table->index(['product_id', 'warehouse_id', 'quantity_remaining'], 'idx_svl_prod_wh_qty_rem');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_valuation_layers');
    }
};
