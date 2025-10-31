<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Currency;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get default currency (PKR or first available)
        $defaultCurrency = Currency::where('currency_code', 'PKR')->first() ?? Currency::first();

        Company::create([
            'company_name' => 'Moon Traders',
            'abbr' => 'MT',
            'country' => 'Pakistan',
            'tax_id' => '1234567-8',
            'phone_no' => '+92-300-1234567',
            'email' => 'info@moontraders.com',
            'website' => 'https://moontraders.com',
            'company_description' => 'Leading trading and import-export company',
            'date_of_establishment' => '2020-01-01',
            'date_of_incorporation' => '2020-01-15',
            'default_currency_id' => $defaultCurrency?->id,
            'is_group' => false,
            'enable_perpetual_inventory' => true,
            'credit_limit' => 1000000,
            'monthly_sales_target' => 500000,
            'lft' => 1,
            'rgt' => 2,
        ]);
    }
}
