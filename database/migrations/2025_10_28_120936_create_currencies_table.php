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
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('currency_code', 3)->unique()->comment('ISO 4217 currency code (e.g., USD, PKR, EUR).');
            $table->string('currency_name')->comment('Full name of the currency (e.g., US Dollar, Pakistani Rupee).');
            $table->string('currency_symbol', 10)->comment('Currency symbol (e.g., $, ₨, €).');
            $table->decimal('exchange_rate', 15, 6)->default(1.000000)->comment('Exchange rate relative to base currency.');
            $table->boolean('is_base_currency')->default(false)->comment('True if this is the base/home currency.');
            $table->boolean('is_active')->default(true)->comment('If false, currency cannot be used in new transactions.');
            $table->timestamps();

            // Index for quick lookups
            $table->index('currency_code');
            $table->index('is_base_currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
