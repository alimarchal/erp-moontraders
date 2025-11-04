<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Models\Currency;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultCurrency = Currency::where('currency_code', 'PKR')->first();

        $suppliers = [
            [
                'supplier_name' => 'Dalda Foods',
                'short_name' => 'Dalda',
                'country' => 'Pakistan',
                'supplier_group' => 'Local',
                'supplier_type' => 'Edible Oils & Ghee',
                'is_transporter' => false,
                'is_internal_supplier' => false,
                'default_currency_id' => $defaultCurrency?->id,
            ],
            [
                'supplier_name' => 'Meezan Oil & Ghee',
                'short_name' => 'Meezan',
                'country' => 'Pakistan',
                'supplier_group' => 'Local',
                'supplier_type' => 'Edible Oils & Ghee',
                'is_transporter' => false,
                'is_internal_supplier' => false,
                'default_currency_id' => $defaultCurrency?->id,
            ],
            [
                'supplier_name' => 'Nestlé Pakistan',
                'short_name' => 'Nestle',
                'country' => 'Pakistan',
                'supplier_group' => 'Multinational',
                'supplier_type' => 'Food & Beverage',
                'is_transporter' => false,
                'is_internal_supplier' => false,
                'default_currency_id' => $defaultCurrency?->id,
            ],
            [
                'supplier_name' => 'Engro Corporation',
                'short_name' => 'Engro',
                'country' => 'Pakistan',
                'supplier_group' => 'Local',
                'supplier_type' => 'Conglomerate',
                'is_transporter' => false,
                'is_internal_supplier' => false,
                'default_currency_id' => $defaultCurrency?->id,
            ],
            [
                'supplier_name' => 'Unilever Pakistan',
                'short_name' => 'Unilever',
                'country' => 'Pakistan',
                'supplier_group' => 'Multinational',
                'supplier_type' => 'FMCG',
                'is_transporter' => false,
                'is_internal_supplier' => false,
                'default_currency_id' => $defaultCurrency?->id,
            ],
            [
                'supplier_name' => 'Lipton Pakistan',
                'short_name' => 'Lipton',
                'country' => 'Pakistan',
                'supplier_group' => 'Multinational',
                'supplier_type' => 'Tea & Beverages',
                'is_transporter' => false,
                'is_internal_supplier' => false,
                'default_currency_id' => $defaultCurrency?->id,
            ],
            [
                'supplier_name' => 'Kausar Oil & Ghee',
                'short_name' => 'Kausar',
                'country' => 'Pakistan',
                'supplier_group' => 'Local',
                'supplier_type' => 'Edible Oils & Ghee',
                'is_transporter' => false,
                'is_internal_supplier' => false,
                'default_currency_id' => $defaultCurrency?->id,
            ],
            [
                'supplier_name' => 'English Biscuit Manufacturers',
                'short_name' => 'EBM',
                'country' => 'Pakistan',
                'supplier_group' => 'Local',
                'supplier_type' => 'Biscuits & Confectionery',
                'is_transporter' => false,
                'is_internal_supplier' => false,
                'default_currency_id' => $defaultCurrency?->id,
            ],
            [
                'supplier_name' => 'National Foods',
                'short_name' => 'National',
                'country' => 'Pakistan',
                'supplier_group' => 'Local',
                'supplier_type' => 'Spices & Food Products',
                'is_transporter' => false,
                'is_internal_supplier' => false,
                'default_currency_id' => $defaultCurrency?->id,
            ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::create($supplier);
        }
    }
}