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
                'supplier_name' => 'ABC Trading Co.',
                'country' => 'Pakistan',
                'supplier_group' => 'Local',
                'supplier_type' => 'Company',
                'is_transporter' => false,
                'is_internal_supplier' => false,
                'default_currency_id' => $defaultCurrency?->id,
                'supplier_details' => 'Leading supplier of electronics',
                'website' => 'https://abctrading.pk',
            ],
            [
                'supplier_name' => 'XYZ Imports Ltd.',
                'country' => 'Pakistan',
                'supplier_group' => 'Local',
                'supplier_type' => 'Company',
                'is_transporter' => false,
                'is_internal_supplier' => false,
                'default_currency_id' => $defaultCurrency?->id,
                'supplier_details' => 'Import goods distributor',
                'website' => 'https://xyzimports.pk',
            ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::create($supplier);
        }
    }
}
