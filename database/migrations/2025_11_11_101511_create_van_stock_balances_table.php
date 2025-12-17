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
        Schema::create('van_stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('opening_balance', 15, 3)->default(0)->comment('Previous day closing = today opening');
            $table->decimal('quantity_on_hand', 15, 3)->default(0);
            $table->decimal('average_cost', 15, 2)->default(0);
            $table->timestamp('last_updated')->useCurrent();
            $table->timestamps();

            $table->unique(['vehicle_id', 'product_id']);
            $table->index('vehicle_id');
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('van_stock_balances');
    }
};
