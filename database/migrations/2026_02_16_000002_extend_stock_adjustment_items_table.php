<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_adjustment_items', function (Blueprint $table) {
            $table->foreignId('stock_batch_id')->nullable()->after('product_id')
                ->constrained('stock_batches')->nullOnDelete();
            $table->foreignId('grn_item_id')->nullable()->after('stock_batch_id')
                ->constrained('goods_receipt_note_items')->nullOnDelete();

            $table->index('stock_batch_id');
            $table->index('grn_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_adjustment_items', function (Blueprint $table) {
            $table->dropForeign(['stock_batch_id']);
            $table->dropColumn('stock_batch_id');
            $table->dropForeign(['grn_item_id']);
            $table->dropColumn('grn_item_id');
        });
    }
};
