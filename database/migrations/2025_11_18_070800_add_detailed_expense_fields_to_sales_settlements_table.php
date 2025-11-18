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
            // Add detailed expense fields
            $table->decimal('expense_toll_tax', 12, 2)->default(0)->after('expenses_claimed');
            $table->decimal('expense_amr_powder_claim', 12, 2)->default(0)->after('expense_toll_tax');
            $table->decimal('expense_amr_liquid_claim', 12, 2)->default(0)->after('expense_amr_powder_claim');
            $table->decimal('expense_scheme', 12, 2)->default(0)->after('expense_amr_liquid_claim');
            $table->decimal('expense_advance_tax', 12, 2)->default(0)->after('expense_scheme');
            $table->decimal('expense_food_charges', 12, 2)->default(0)->after('expense_advance_tax');
            $table->decimal('expense_salesman_charges', 12, 2)->default(0)->after('expense_food_charges');
            $table->decimal('expense_loader_charges', 12, 2)->default(0)->after('expense_salesman_charges');
            $table->decimal('expense_percentage', 12, 2)->default(0)->after('expense_loader_charges');
            $table->decimal('expense_message_amount', 12, 2)->default(0)->after('expense_percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_settlements', function (Blueprint $table) {
            $table->dropColumn([
                'expense_toll_tax',
                'expense_amr_powder_claim',
                'expense_amr_liquid_claim',
                'expense_scheme',
                'expense_advance_tax',
                'expense_food_charges',
                'expense_salesman_charges',
                'expense_loader_charges',
                'expense_percentage',
                'expense_message_amount',
            ]);
        });
    }
};
