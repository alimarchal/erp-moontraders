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
        Schema::create('customer_credit_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_settlement_id')->constrained('sales_settlements')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('employees')->onDelete('restrict');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('restrict');
            $table->string('invoice_number')->nullable();
            $table->decimal('sale_amount', 15, 2);
            $table->decimal('recovery_amount', 15, 2)->default(0)->after('sale_amount');
            $table->decimal('previous_balance', 15, 2)->default(0)->after('recovery_amount');
            $table->decimal('new_balance', 15, 2)->default(0)->after('previous_balance');
            $table->text('notes')->nullable();
            $table->string('status')->default('draft')->comment('Status: draft, posted, cancelled');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_credit_sales');
    }
};
