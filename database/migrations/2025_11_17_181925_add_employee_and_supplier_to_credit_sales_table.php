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
        Schema::table('credit_sales', function (Blueprint $table) {
            $table->foreignId('employee_id')->after('sales_settlement_id')->constrained('employees')->onDelete('restrict');
            $table->foreignId('supplier_id')->after('employee_id')->nullable()->constrained('suppliers')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credit_sales', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropForeign(['supplier_id']);
            $table->dropColumn(['employee_id', 'supplier_id']);
        });
    }
};
