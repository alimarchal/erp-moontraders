<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_recall_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_recall_id')->constrained('product_recalls')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('stock_batch_id')->constrained('stock_batches')->cascadeOnDelete();
            $table->foreignId('grn_item_id')->nullable()->constrained('goods_receipt_note_items')->nullOnDelete();
            $table->decimal('quantity_recalled', 15, 3);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('total_value', 15, 2);
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('product_recall_id');
            $table->index('stock_batch_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_recall_items');
    }
};
