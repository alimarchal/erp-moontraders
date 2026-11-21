<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A GRN Correction fixes a posted GRN line (its conversion factor, or its product while the batch is untouched)
     * without overwriting what was posted: it records the old and new figures, the reason and who did it, and posts
     * the quantity difference as a stock movement of its own.
     */
    public function up(): void
    {
        Schema::create('grn_corrections', function (Blueprint $table) {
            $table->id();
            $table->string('correction_number', 40)->unique();
            $table->foreignId('goods_receipt_note_id')->constrained('goods_receipt_notes');
            $table->text('reason');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->decimal('cogs_delta', 15, 2)->default(0);
            $table->unsignedInteger('recosted_movements')->default(0);
            $table->foreignId('corrected_by')->constrained('users');
            $table->timestamp('corrected_at');
            $table->timestamps();
        });

        Schema::create('grn_correction_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grn_correction_id')->constrained('grn_corrections')->cascadeOnDelete();
            $table->foreignId('goods_receipt_note_item_id')->constrained('goods_receipt_note_items');
            $table->foreignId('stock_batch_id')->constrained('stock_batches');
            $table->foreignId('old_product_id')->constrained('products');
            $table->foreignId('new_product_id')->constrained('products');
            $table->decimal('old_conversion_factor', 15, 4);
            $table->decimal('new_conversion_factor', 15, 4);
            $table->decimal('old_quantity', 15, 2);
            $table->decimal('new_quantity', 15, 2);
            $table->decimal('old_unit_cost', 15, 6);
            $table->decimal('new_unit_cost', 15, 6);
            $table->decimal('line_value', 15, 4);
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grn_correction_lines');
        Schema::dropIfExists('grn_corrections');
    }
};
