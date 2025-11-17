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
        Schema::create('sales_settlement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_settlement_id')->constrained('sales_settlements')->cascadeOnDelete();
            $table->foreignId('goods_issue_item_id')->nullable()->constrained('goods_issue_items')->nullOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity_issued', 15, 3);
            $table->decimal('quantity_sold', 15, 3);
            $table->decimal('quantity_returned', 15, 3)->default(0);
            $table->decimal('quantity_shortage', 15, 3)->default(0);
            $table->decimal('unit_selling_price', 15, 2);
            $table->decimal('total_sales_value', 15, 2);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('total_cogs', 15, 2);
            $table->timestamps();

            $table->index('sales_settlement_id');
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_settlement_items');
    }
};
