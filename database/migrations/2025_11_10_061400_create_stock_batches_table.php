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
        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();

            // Batch Identity
            $table->string('batch_code', 100)->unique()->comment('Auto-generated: BATCH-2025-0001');
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            // Source Information
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->date('receipt_date');
            $table->string('supplier_batch_number', 100)->nullable()->comment('Supplier\'s batch/lot number');
            $table->string('lot_number', 100)->nullable()->comment('Internal lot code from GRN');

            // Dates
            $table->date('manufacturing_date')->nullable();
            $table->date('expiry_date')->nullable();

            // Promotional Information
            $table->foreignId('promotional_campaign_id')->nullable()->constrained('promotional_campaigns')->nullOnDelete();
            $table->boolean('is_promotional')->default(false)->index();
            $table->decimal('promotional_selling_price', 15, 2)->nullable()->comment('Special promotional price');
            $table->decimal('promotional_discount_percent', 5, 2)->nullable();

            // Priority Selling
            $table->date('must_sell_before')->nullable()->comment('Supplier condition: sell before this date');
            $table->integer('priority_order')->default(99)->comment('1=Highest priority (sell first), 99=Normal FIFO');
            $table->enum('selling_strategy', ['fifo', 'lifo', 'priority', 'expiry_first'])->default('fifo');

            // Costing
            $table->decimal('unit_cost', 15, 2)->comment('Purchase cost per unit');
            $table->decimal('selling_price', 15, 2)->nullable()->comment('Selling price from GRN');

            // Status
            $table->enum('status', ['active', 'depleted', 'expired', 'recalled'])->default('active');
            $table->boolean('is_active')->default(true);

            $table->text('notes')->nullable();
            $table->string('storage_location', 100)->nullable()->comment('Warehouse rack/bin location');
            $table->timestamps();

            // Indexes
            $table->index('product_id');
            $table->index('supplier_id');
            $table->index('expiry_date');
            $table->index('priority_order');
            $table->index(['product_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_batches');
    }
};
