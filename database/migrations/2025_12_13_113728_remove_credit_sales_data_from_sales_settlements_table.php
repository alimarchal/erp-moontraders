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
            $table->dropColumn('credit_sales_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_settlements', function (Blueprint $table) {
            $table->json('credit_sales_data')->nullable()->after('credit_sales_amount')
                ->comment('JSON array of draft credit sales data - processed when settlement is posted');
        });
    }
};
