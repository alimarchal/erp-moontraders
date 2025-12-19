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
        Schema::create('sales_settlement_credit_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_settlement_id')->constrained('sales_settlements')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('invoice_number')->nullable();
            $table->decimal('sale_amount', 15, 2)->default(0)->comment('Credit sale amount');
            $table->decimal('payment_received', 15, 2)->default(0)->comment('Cash payment received with this sale');
            $table->decimal('previous_balance', 15, 2)->default(0)->comment('Customer balance before this sale');
            $table->decimal('new_balance', 15, 2)->default(0)->comment('Customer balance after this sale');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('sales_settlement_id');
            $table->index('customer_id');
            $table->index('employee_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_settlement_credit_sales');
    }
};
