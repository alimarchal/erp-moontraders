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
        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained('stock_adjustments')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('system_quantity', 15, 3);
            $table->decimal('actual_quantity', 15, 3);
            $table->decimal('adjustment_quantity', 15, 3);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('adjustment_value', 15, 2);
            $table->foreignId('uom_id')->constrained('uoms')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('stock_adjustment_id');
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_items');
    }
};
