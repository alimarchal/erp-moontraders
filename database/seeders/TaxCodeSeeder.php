<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TaxCodeSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        // Clear the table to avoid conflicts on re-seed
        DB::table('tax_codes')->delete();

        // Get the tax payable and receivable accounts
        $gstPayableAccount = DB::table('chart_of_accounts')
            ->where('account_name', 'General Sales Tax (GST)')
            ->first();

        $taxReceivableAccount = DB::table('chart_of_accounts')
            ->where('account_name', 'Advance Tax')
            ->first();

        if (! $gstPayableAccount || ! $taxReceivableAccount) {
            throw new \Exception('Required tax accounts not found. Please run ChartOfAccountSeeder first.');
        }

        // Define tax codes
        $taxCodes = [
            [
                'tax_code' => 'GST-18',
                'name' => 'GST @ 18%',
                'description' => 'Goods and Services Tax at 18% - Standard rate for most goods and services',
                'tax_type' => 'gst',
                'calculation_method' => 'percentage',
                'tax_payable_account_id' => $gstPayableAccount->id,
                'tax_receivable_account_id' => $taxReceivableAccount->id,
                'is_active' => true,
                'is_compound' => false,
                'included_in_price' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tax_code' => 'VAT-15',
                'name' => 'VAT @ 15%',
                'description' => 'Value Added Tax at 15% - Standard VAT rate',
                'tax_type' => 'vat',
                'calculation_method' => 'percentage',
                'tax_payable_account_id' => $gstPayableAccount->id,
                'tax_receivable_account_id' => $taxReceivableAccount->id,
                'is_active' => true,
                'is_compound' => false,
                'included_in_price' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tax_code' => 'ST-10',
                'name' => 'Sales Tax @ 10%',
                'description' => 'Sales Tax at 10% - Standard sales tax rate',
                'tax_type' => 'sales_tax',
                'calculation_method' => 'percentage',
                'tax_payable_account_id' => $gstPayableAccount->id,
                'tax_receivable_account_id' => $taxReceivableAccount->id,
                'is_active' => true,
                'is_compound' => false,
                'included_in_price' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tax_code' => 'GST-12',
                'name' => 'GST @ 12%',
                'description' => 'Goods and Services Tax at 12% - Reduced rate for essential items',
                'tax_type' => 'gst',
                'calculation_method' => 'percentage',
                'tax_payable_account_id' => $gstPayableAccount->id,
                'tax_receivable_account_id' => $taxReceivableAccount->id,
                'is_active' => true,
                'is_compound' => false,
                'included_in_price' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tax_code' => 'GST-5',
                'name' => 'GST @ 5%',
                'description' => 'Goods and Services Tax at 5% - Lower rate for basic necessities',
                'tax_type' => 'gst',
                'calculation_method' => 'percentage',
                'tax_payable_account_id' => $gstPayableAccount->id,
                'tax_receivable_account_id' => $taxReceivableAccount->id,
                'is_active' => true,
                'is_compound' => false,
                'included_in_price' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tax_code' => 'GST-0',
                'name' => 'GST @ 0% (Zero-Rated)',
                'description' => 'Zero-rated GST for exports and exempt items',
                'tax_type' => 'gst',
                'calculation_method' => 'percentage',
                'tax_payable_account_id' => $gstPayableAccount->id,
                'tax_receivable_account_id' => $taxReceivableAccount->id,
                'is_active' => true,
                'is_compound' => false,
                'included_in_price' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        // Insert tax codes
        DB::table('tax_codes')->insert($taxCodes);

        $this->command->info('Tax codes seeded successfully.');
    }
}
