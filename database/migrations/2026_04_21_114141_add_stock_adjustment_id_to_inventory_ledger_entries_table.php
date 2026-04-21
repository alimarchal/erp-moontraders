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
        Schema::table('inventory_ledger_entries', function (Blueprint $table) {
            $table->foreignId('stock_adjustment_id')->nullable()->after('sales_settlement_id')->constrained('stock_adjustments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_ledger_entries', function (Blueprint $table) {
            $table->dropForeign(['stock_adjustment_id']);
            $table->dropColumn('stock_adjustment_id');
        });
    }
};
