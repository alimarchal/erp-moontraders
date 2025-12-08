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
        Schema::create('sales_settlement_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_settlement_id')->constrained('sales_settlements')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->enum('sale_type', ['cash', 'cheque', 'credit'])->default('cash');
            $table->string('invoice_number')->nullable();
            $table->decimal('sale_amount', 15, 2);
            $table->string('cheque_number')->nullable();
            $table->date('cheque_date')->nullable();
            $table->string('bank_name')->nullable();
            $table->enum('payment_status', ['pending', 'cleared', 'bounced'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('sales_settlement_id');
            $table->index('customer_id');
            $table->index(['sale_type', 'payment_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_settlement_sales');
    }
};
