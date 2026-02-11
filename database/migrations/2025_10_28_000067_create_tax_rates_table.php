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
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_code_id')->constrained('tax_codes')->cascadeOnDelete();

            $table->decimal('rate', 5, 2); // Percentage or fixed amount
            $table->date('effective_from');
            $table->date('effective_to')->nullable();

            $table->string('region', 100)->nullable(); // For regional tax variations
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['tax_code_id', 'effective_from', 'effective_to']);
            $table->index(['is_active', 'effective_from']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};
