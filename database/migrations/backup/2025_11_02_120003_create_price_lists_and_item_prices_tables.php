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
        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->string('price_list_name')->unique();
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->boolean('is_selling')->default(true);
            $table->boolean('is_buying')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'is_selling'], 'pl_active_sell_idx');
        });

        Schema::create('item_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_id')->constrained('price_lists')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('uom_id')->nullable()->constrained('uoms')->nullOnDelete();
            $table->decimal('rate', 15, 2);
            $table->date('valid_from')->nullable();
            $table->date('valid_upto')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['price_list_id', 'product_id', 'uom_id', 'valid_from'], 'item_price_unique');
            $table->index(['product_id', 'is_active'], 'iprod_active_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_prices');
        Schema::dropIfExists('price_lists');
    }
};
