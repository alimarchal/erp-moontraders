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
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity', 15, 2);
            $table->decimal('unit_price', 15, 2)->comment('Selling price per unit');
            $table->decimal('total_amount', 15, 2)->comment('quantity * unit_price (Revenue)');
            $table->decimal('unit_cost', 15, 2)->default(0)->comment('Cost per unit at time of sale');
            $table->decimal('total_cost', 15, 2)->default(0)->comment('quantity * unit_cost (COGS)');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sale_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
