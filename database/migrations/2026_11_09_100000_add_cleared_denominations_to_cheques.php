<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional note-by-note count of the cash a salesman brings back when a cheque clears, so the cash collection
     * report can count those notes with the rest. Empty means "amount only", exactly as before.
     */
    public function up(): void
    {
        Schema::table('sales_settlement_cheques', function (Blueprint $table) {
            foreach ([5000, 1000, 500, 100, 50, 20, 10] as $note) {
                $table->unsignedInteger('cleared_denom_'.$note)->nullable();
            }
            $table->decimal('cleared_denom_coins', 15, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sales_settlement_cheques', function (Blueprint $table) {
            $table->dropColumn(['cleared_denom_5000', 'cleared_denom_1000', 'cleared_denom_500', 'cleared_denom_100', 'cleared_denom_50', 'cleared_denom_20', 'cleared_denom_10', 'cleared_denom_coins']);
        });
    }
};
