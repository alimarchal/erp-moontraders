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
        Schema::create('goods_receipt_note_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('grn_id')->constrained('goods_receipt_notes')->cascadeOnDelete();
            $table->integer('line_no')->default(0)->comment('Line sequence in GRN');

            // Product linked to products catelog where product_id likned with supplier
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('stock_uom_id')->nullable()->constrained('uoms')->comment('Base UOM for inventory storage (Piece, Kg, Liter)');

            $table->foreignId('purchase_uom_id')->constrained('uoms')->comment('UOM supplier sells in (Case, Carton, Box)');
            $table->decimal('qty_in_purchase_uom', 15, 2)->comment('Quantity as per invoice (10 Cases)');

            $table->decimal('uom_conversion_factor', 15, 4)->default(1)->comment('1 Purchase UOM = X Stock UOM (e.g., 1 Case = 24 Pieces)');
            $table->decimal('qty_in_stock_uom', 15, 2)->comment('Converted quantity for inventory (240 Pieces)');

            // Case-based Purchasing
            $table->decimal('unit_price_per_case', 15, 2)->nullable()->comment('Invoice price per case');
            $table->decimal('extended_value', 15, 2)->nullable()->default(0)->comment('qty_cases × unit_price_per_case');

            // Tax and Discount Fields
            $table->decimal('discount_value', 15, 2)->nullable()->default(0)->comment('Discount amount');
            $table->decimal('fmr_allowance', 15, 2)->nullable()->default(0)->comment('Free Market Rate allowance');
            $table->decimal('discounted_value_before_tax', 15, 2)->nullable()->default(0)->comment('extended_value - discount - fmr_allowance');
            $table->decimal('excise_duty', 15, 2)->nullable()->default(0)->comment('Excise duty amount');
            $table->decimal('sales_tax_value', 15, 2)->nullable()->default(0)->comment('Sales tax amount');
            $table->decimal('advance_income_tax', 15, 2)->nullable()->default(0)->comment('Advance income tax amount');
            $table->decimal('total_value_with_taxes', 15, 2)->nullable()->default(0)->comment('Final total including all taxes');

            // Quantities
            $table->decimal('quantity_ordered', 15, 2)->nullable()->comment('From PO if exists');
            $table->decimal('quantity_received', 15, 2)->comment('Physical qty received (auto-calculated from cases)');
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
