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
            $table->decimal('gross_profit', 15, 2)->nullable()->after('expenses_claimed')
                ->comment('Calculated: total_sales_amount - total_cogs');
            $table->decimal('total_cogs', 15, 2)->nullable()->after('gross_profit')
                ->comment('Total cost of goods sold');
            $table->json('bank_transfers')->nullable()->after('bank_account_id')
                ->comment('Array of bank transfers with bank_account_id and amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_settlements', function (Blueprint $table) {
            $table->dropColumn(['gross_profit', 'total_cogs', 'bank_transfers']);
        });
    }
};
