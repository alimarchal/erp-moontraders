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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();

            // Movement Type
            $table->enum('movement_type', [
                'grn',           // Goods Receipt Note
                'goods_issue',   // Issue to vehicle/customer
                'goods_return',  // Return from vehicle
                'transfer',      // Warehouse to warehouse
                'adjustment',    // Stock correction
                'damage',        // Damaged/expired
                'theft',         // Theft/loss
                'sale',          // Sales settlement
                'return',        // Sales return to warehouse
                'shortage',      // Sales shortage/loss
            ])->index();

            // Polymorphic Reference
            $table->string('reference_type', 100)->nullable()->comment('GoodsReceiptNote, GoodsIssue, etc.');
            $table->unsignedBigInteger('reference_id')->nullable();

            // Date & Product
            $table->date('movement_date')->index();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            // Batch Tracking
            $table->foreignId('stock_batch_id')->nullable()->constrained('stock_batches')->nullOnDelete();

            // Location
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('from_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();

            // Quantity & Cost
            $table->decimal('quantity', 15, 2)->comment('Positive=IN, Negative=OUT');
            $table->foreignId('uom_id')->constrained('uoms');
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('total_value', 15, 2)->comment('abs(quantity) × unit_cost');

            // User
            $table->foreignId('created_by')->constrained('users');

            $table->timestamps();

            // Indexes
            $table->index('product_id');
            $table->index('stock_batch_id');
            $table->index('warehouse_id');
            $table->index(['reference_type', 'reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
