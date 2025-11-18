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

            // Bank transfer/online payment details
            $table->decimal('bank_transfer_amount', 15, 2)->default(0)->after('denom_coins');
            $table->foreignId('bank_account_id')->nullable()->after('bank_transfer_amount')->constrained('bank_accounts')->nullOnDelete();

            // Cheque details
            $table->integer('cheque_count')->default(0)->after('bank_account_id');
            $table->json('cheque_details')->nullable()->after('cheque_count')->comment('Stores array of cheque details: [{cheque_number, amount, bank_name, date}]');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_settlements', function (Blueprint $table) {
            $table->dropForeign(['bank_account_id']);
            $table->dropColumn([
                'denom_5000',
                'denom_1000',
                'denom_500',
                'denom_100',
                'denom_50',
                'denom_20',
                'denom_10',
                'denom_coins',
                'bank_transfer_amount',
                'bank_account_id',
                'cheque_count',
                'cheque_details',
            ]);
        });
    }
};
