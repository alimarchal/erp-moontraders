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
        Schema::table('sales_settlements', function (Blueprint $table) {
            $table->decimal('bank_sales_amount', 15, 2)->default(0)->after('cheque_sales_amount');
        });

        Schema::table('sales_settlement_cash_denominations', function (Blueprint $table) {
            $table->decimal('total_amount', 15, 2)->default(0)->after('denom_coins');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_settlements', function (Blueprint $table) {
            $table->dropColumn('bank_sales_amount');
        });

        Schema::table('sales_settlement_cash_denominations', function (Blueprint $table) {
            $table->dropColumn('total_amount');
        });
    }
};
