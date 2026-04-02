<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add 4250 Settlement Excess Income under 4200 Indirect Income.
        // Only insert if the prerequisite data (account_types, currencies, parent account) exists,
        // which is the case in production after seeding. Tests seed their own COA data independently.
        $parent = DB::table('chart_of_accounts')->where('account_code', '4200')->first();
        $currency = DB::table('currencies')->where('is_base_currency', true)->first()
            ?? DB::table('currencies')->first();
        $accountType = DB::table('chart_of_accounts')
            ->whereIn('account_code', ['4240', '4230', '4220', '4210', '4110'])
            ->whereNotNull('account_type_id')
            ->value('account_type_id')
            ?? DB::table('account_types')->where('type_name', 'Income')->value('id');

        $alreadyExists = DB::table('chart_of_accounts')->where('account_code', '4250')->exists();

        if (! $alreadyExists && $parent && $currency && $accountType) {
            DB::table('chart_of_accounts')->insert([
                'parent_id' => $parent->id,
                'account_type_id' => $accountType,
                'currency_id' => $currency->id,
                'account_code' => '4250',
                'account_name' => 'Settlement Excess Income',
                'normal_balance' => 'credit',
                'description' => 'Excess cash received from salesmen during settlement posting.',
                'is_group' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::create('sales_settlement_excess_amounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_settlement_id')->constrained('sales_settlements')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('sales_settlement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_settlement_excess_amounts');
        DB::table('chart_of_accounts')->where('account_code', '4250')->delete();
    }
};
