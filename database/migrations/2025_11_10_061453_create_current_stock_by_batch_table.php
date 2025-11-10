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
        Schema::create('current_stock_by_batch', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('stock_batch_id')->constrained('stock_batches')->cascadeOnDelete();

            // Quantities
            $table->decimal('quantity_on_hand', 15, 2)->default(0);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('total_value', 15, 2)->storedAs('quantity_on_hand * unit_cost');

            // Promotional Info
            $table->boolean('is_promotional')->default(false)->index();
            $table->decimal('promotional_price', 15, 2)->nullable();
            $table->integer('priority_order')->default(99)->index();
            $table->date('must_sell_before')->nullable()->index();
            $table->date('expiry_date')->nullable()->index();

            // Status
            $table->enum('status', ['active', 'depleted', 'expired'])->default('active')->index();

            $table->timestamp('last_updated')->useCurrent();

            // Unique constraint
            $table->unique(['product_id', 'warehouse_id', 'stock_batch_id'], 'unique_batch_location');

            // Indexes
            $table->index('product_id');
            $table->index('stock_batch_id');
            $table->index(['product_id', 'warehouse_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('current_stock_by_batch');
    }
};
