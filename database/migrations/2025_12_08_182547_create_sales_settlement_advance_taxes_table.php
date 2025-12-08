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
        Schema::create('sales_settlement_advance_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_settlement_id')->constrained('sales_settlements')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->decimal('sale_amount', 15, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0.25);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->string('invoice_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('sales_settlement_id');
            $table->index('customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_settlement_advance_taxes');
    }
};
