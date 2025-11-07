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
        Schema::create('delivery_note_items', function (Blueprint $table) {
            $table->id();

            // Parent Reference
            $table->foreignId('delivery_note_id')->constrained('delivery_notes')->cascadeOnDelete();

            // Product Information
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('uom_id')->nullable()->constrained('uoms')->nullOnDelete();

            // Quantity Tracking (following ERPNext pattern)
            $table->decimal('loaded_qty', 15, 2)->comment('Quantity loaded onto vehicle');
            $table->decimal('delivered_qty', 15, 2)->default(0)->comment('Quantity successfully delivered');
            $table->decimal('returned_qty', 15, 2)->default(0)->comment('Quantity returned/undelivered');
            $table->decimal('pending_qty', 15, 2)->storedAs('loaded_qty - delivered_qty - returned_qty')
                ->comment('Remaining quantity to be delivered');

            // Pricing
            $table->decimal('rate', 15, 2)->comment('Rate per unit');
            $table->decimal('amount', 15, 2)->storedAs('loaded_qty * rate')->comment('Total line amount');
            $table->decimal('delivered_amount', 15, 2)->storedAs('delivered_qty * rate')
                ->comment('Amount for delivered quantity');

            // References
            $table->foreignId('sale_item_id')->nullable()->constrained('sale_items')->nullOnDelete()
                ->comment('Links back to sales order item');
            $table->foreignId('stock_receipt_item_id')->nullable()->constrained('stock_receipt_items')->nullOnDelete()
                ->comment('Source stock receipt if applicable');

            // Cost Tracking (for COGS calculation)
            $table->decimal('cost_per_unit', 15, 2)->comment('Cost per unit for COGS');
            $table->decimal('total_cost', 15, 2)->storedAs('loaded_qty * cost_per_unit')
                ->comment('Total cost of goods');
            $table->decimal('cogs_amount', 15, 2)->storedAs('delivered_qty * cost_per_unit')
                ->comment('Cost of Goods Sold for delivered items');

            // Item Condition Tracking
            $table->enum('item_status', ['good', 'damaged', 'returned'])->default('good');
            $table->text('notes')->nullable()->comment('Item-specific notes');

            // Batch and Serial Number Tracking (for traceability)
            $table->string('batch_number')->nullable();
            $table->text('serial_numbers')->nullable()->comment('Comma-separated serial numbers');

            $table->timestamps();

            // Indexes for reporting
            $table->index(['delivery_note_id', 'product_id']);
            $table->index('sale_item_id');
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_note_items');
    }
};
