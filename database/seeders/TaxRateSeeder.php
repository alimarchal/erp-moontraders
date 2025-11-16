<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TaxRateSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        // Clear the table to avoid conflicts on re-seed
        DB::table('tax_rates')->delete();

        // Get all tax codes
        $taxCodes = DB::table('tax_codes')->get()->keyBy('tax_code');

        if ($taxCodes->isEmpty()) {
            throw new \Exception('No tax codes found. Please run TaxCodeSeeder first.');
        }

        // Define tax rates for each tax code
        $taxRates = [];

        // GST @ 18%
        if (isset($taxCodes['GST-18'])) {
            $taxRates[] = [
                'tax_code_id' => $taxCodes['GST-18']->id,
                'rate' => 18.00,
                'effective_from' => Carbon::create(2024, 1, 1),
                'effective_to' => null,
                'region' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // VAT @ 15%
        if (isset($taxCodes['VAT-15'])) {
            $taxRates[] = [
                'tax_code_id' => $taxCodes['VAT-15']->id,
                'rate' => 15.00,
                'effective_from' => Carbon::create(2024, 1, 1),
                'effective_to' => null,
                'region' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Sales Tax @ 10%
        if (isset($taxCodes['ST-10'])) {
            $taxRates[] = [
                'tax_code_id' => $taxCodes['ST-10']->id,
                'rate' => 10.00,
                'effective_from' => Carbon::create(2024, 1, 1),
                'effective_to' => null,
                'region' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // GST @ 12%
        if (isset($taxCodes['GST-12'])) {
            $taxRates[] = [
                'tax_code_id' => $taxCodes['GST-12']->id,
                'rate' => 12.00,
                'effective_from' => Carbon::create(2024, 1, 1),
                'effective_to' => null,
                'region' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // GST @ 5%
        if (isset($taxCodes['GST-5'])) {
            $taxRates[] = [
                'tax_code_id' => $taxCodes['GST-5']->id,
                'rate' => 5.00,
                'effective_from' => Carbon::create(2024, 1, 1),
                'effective_to' => null,
                'region' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // GST @ 0% (Zero-Rated)
        if (isset($taxCodes['GST-0'])) {
            $taxRates[] = [
                'tax_code_id' => $taxCodes['GST-0']->id,
                'rate' => 0.00,
                'effective_from' => Carbon::create(2024, 1, 1),
                'effective_to' => null,
                'region' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Insert tax rates
        if (!empty($taxRates)) {
            DB::table('tax_rates')->insert($taxRates);
            $this->command->info('Tax rates seeded successfully.');
        } else {
            $this->command->warn('No tax rates to seed. Check tax codes.');
        }
    }
}
