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
        Schema::create('sales_settlement_item_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_settlement_item_id')->constrained('sales_settlement_items')->cascadeOnDelete();
            $table->foreignId('stock_batch_id')->constrained('stock_batches');
            $table->string('batch_code');
            $table->decimal('quantity_issued', 15, 3)->default(0);
            $table->decimal('quantity_sold', 15, 3)->default(0);
            $table->decimal('quantity_returned', 15, 3)->default(0);
            $table->decimal('quantity_shortage', 15, 3)->default(0);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('selling_price', 15, 2);
            $table->boolean('is_promotional')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_settlement_item_batches');
    }
};
