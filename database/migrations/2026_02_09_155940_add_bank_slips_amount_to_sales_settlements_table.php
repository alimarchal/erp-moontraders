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
        Schema::table('sales_settlements', function (Blueprint $table) {
            $table->decimal('bank_slips_amount', 15, 2)->default(0)->after('cash_sales_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_settlements', function (Blueprint $table) {
            $table->dropColumn('bank_slips_amount');
        });
    }
};
