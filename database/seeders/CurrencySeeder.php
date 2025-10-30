<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create base currency (PKR - Pakistani Rupee)
        Currency::create([
            'currency_code' => 'PKR',
            'currency_name' => 'Pakistani Rupee',
            'currency_symbol' => '₨',
            'exchange_rate' => 1.000000,
            'is_base_currency' => true,
            'is_active' => true,
        ]);

        // Create other common currencies
        Currency::create([
            'currency_code' => 'USD',
            'currency_name' => 'US Dollar',
            'currency_symbol' => '$',
            'exchange_rate' => 280.000000,
            'is_base_currency' => false,
            'is_active' => true,
        ]);

        Currency::create([
            'currency_code' => 'EUR',
            'currency_name' => 'Euro',
            'currency_symbol' => '€',
            'exchange_rate' => 305.000000,
            'is_base_currency' => false,
            'is_active' => true,
        ]);

        Currency::create([
            'currency_code' => 'GBP',
            'currency_name' => 'British Pound',
            'currency_symbol' => '£',
            'exchange_rate' => 355.000000,
            'is_base_currency' => false,
            'is_active' => true,
        ]);

        Currency::create([
            'currency_code' => 'AED',
            'currency_name' => 'UAE Dirham',
            'currency_symbol' => 'د.إ',
            'exchange_rate' => 76.000000,
            'is_base_currency' => false,
            'is_active' => true,
        ]);

        Currency::create([
            'currency_code' => 'SAR',
            'currency_name' => 'Saudi Riyal',
            'currency_symbol' => 'ر.س',
            'exchange_rate' => 74.500000,
            'is_base_currency' => false,
            'is_active' => true,
        ]);
    }
}
