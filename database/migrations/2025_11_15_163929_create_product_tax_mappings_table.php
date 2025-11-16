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
        Schema::create('product_tax_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('tax_code_id')->constrained('tax_codes')->restrictOnDelete();

            $table->enum('transaction_type', ['sales', 'purchase', 'both'])->default('both');
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['product_id', 'tax_code_id', 'transaction_type'], 'uk_product_tax');
            $table->index('is_active');
            $table->index('transaction_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_tax_mappings');
    }
};
