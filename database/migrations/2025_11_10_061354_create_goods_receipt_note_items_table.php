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
        Schema::create('goods_receipt_note_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('grn_id')->constrained('goods_receipt_notes')->cascadeOnDelete();
            $table->integer('line_no')->default(0)->comment('Line sequence in GRN');

            // Product
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('uom_id')->constrained('uoms');

            // Quantities
            $table->decimal('quantity_ordered', 15, 2)->nullable()->comment('From PO if exists');
            $table->decimal('quantity_received', 15, 2)->comment('Physical qty received');
            $table->decimal('quantity_accepted', 15, 2)->comment('Qty after QC check');
            $table->decimal('quantity_rejected', 15, 2)->default(0)->comment('Damaged/defective qty');

            // Pricing
            $table->decimal('unit_cost', 15, 2)->comment('Purchase price per unit');
            $table->decimal('selling_price', 15, 2)->nullable()->comment('Selling price per unit');
            $table->decimal('total_cost', 15, 2)->comment('quantity_accepted × unit_cost');

            // Batch/Lot Tracking
            $table->string('batch_number', 100)->nullable()->comment('Supplier batch code');
            $table->string('lot_number', 100)->nullable()->comment('Internal lot code');
            $table->date('manufacturing_date')->nullable();
            $table->date('expiry_date')->nullable();

            // Promotional Information
            $table->foreignId('promotional_campaign_id')->nullable()->constrained('promotional_campaigns')->nullOnDelete();
            $table->boolean('is_promotional')->default(false)->index();
            $table->decimal('promotional_price', 15, 2)->nullable()->comment('Special selling price for this batch');
            $table->decimal('promotional_discount_percent', 5, 2)->nullable();
            $table->date('must_sell_before')->nullable()->comment('Supplier condition');
            $table->integer('priority_order')->default(99)->comment('1=Urgent, 99=Normal FIFO');
            $table->enum('selling_strategy', ['fifo', 'lifo', 'priority', 'expiry_first'])->default('fifo');

            // Quality Control
            $table->enum('quality_status', ['pending', 'approved', 'rejected'])->default('approved');
            $table->string('storage_location', 100)->nullable()->comment('Rack/Bin location in warehouse');

            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('grn_id');
            $table->index('product_id');
            $table->index('batch_number');
            $table->index('expiry_date');
            $table->index('priority_order');
            $table->unique(['grn_id', 'line_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_note_items');
    }
};
