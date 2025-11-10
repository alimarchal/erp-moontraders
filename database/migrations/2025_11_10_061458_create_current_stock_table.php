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
        Schema::create('current_stock', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();

            // Quantities
            $table->decimal('quantity_on_hand', 15, 2)->default(0);
            $table->decimal('quantity_reserved', 15, 2)->default(0)->comment('For pending orders');
            $table->decimal('quantity_available', 15, 2)->storedAs('quantity_on_hand - quantity_reserved');

            // Valuation
            $table->decimal('average_cost', 15, 2)->default(0);
            $table->decimal('total_value', 15, 2)->default(0);

            // Batch Summary
            $table->integer('total_batches')->default(0);
            $table->integer('promotional_batches')->default(0);
            $table->integer('priority_batches')->default(0);

            $table->timestamp('last_updated')->useCurrent();

            // Unique constraint
            $table->unique(['product_id', 'warehouse_id']);

            // Indexes
            $table->index('product_id');
            $table->index('warehouse_id');
            $table->index('quantity_on_hand');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('current_stock');
    }
};
