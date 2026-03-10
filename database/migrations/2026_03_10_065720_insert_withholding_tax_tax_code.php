<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Skip if WHT-0.1 already exists
        if (DB::table('tax_codes')->where('tax_code', 'WHT-0.1')->exists()) {
            return;
        }

        $advanceTaxAccount = DB::table('chart_of_accounts')
            ->where('account_name', 'Advance Tax')
            ->first();

        $gstPayableAccount = DB::table('chart_of_accounts')
            ->where('account_name', 'General Sales Tax (GST)')
            ->first();

        DB::table('tax_codes')->insert([
            'tax_code' => 'WHT-0.1',
            'name' => 'Withholding Tax @ 0.1%',
            'description' => 'Withholding Tax at 0.1% - Applicable on Unilever Pakistan GRNs',
            'tax_type' => 'withholding_tax',
            'calculation_method' => 'percentage',
            'tax_payable_account_id' => $gstPayableAccount?->id,
            'tax_receivable_account_id' => $advanceTaxAccount?->id,
            'is_active' => true,
            'is_compound' => false,
            'included_in_price' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('tax_codes')->where('tax_code', 'WHT-0.1')->delete();
    }
};
