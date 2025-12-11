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
            // Cash denomination breakdown
            $table->integer('denom_5000')->default(0)->after('cash_to_deposit');
            $table->integer('denom_1000')->default(0)->after('denom_5000');
            $table->integer('denom_500')->default(0)->after('denom_1000');
            $table->integer('denom_100')->default(0)->after('denom_500');
            $table->integer('denom_50')->default(0)->after('denom_100');
            $table->integer('denom_20')->default(0)->after('denom_50');
            $table->integer('denom_10')->default(0)->after('denom_20');
            $table->decimal('denom_coins', 10, 2)->default(0)->after('denom_10');

            // Note: bank_transfers JSON removed - now using sales_settlement_bank_transfers table
            // Note: cheque_details JSON removed - now using sales_settlement_cheques table
            // This provides better querying, indexing, and scalability
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_settlements', function (Blueprint $table) {
            $table->dropColumn([
                'denom_5000',
                'denom_1000',
                'denom_500',
                'denom_100',
                'denom_50',
                'denom_20',
                'denom_10',
                'denom_coins',
            ]);
        });
    }
};
