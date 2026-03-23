<?php

namespace Database\Seeders;

use App\Models\LegerRegister;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class LegerRegisterSeeder extends Seeder
{
    public function run(): void
    {
        $nestle = Supplier::where('short_name', 'Nestle')->first();

        if (! $nestle) {
            return;
        }

        // Create a mix of ledger entries for Nestle (February 2026)
        LegerRegister::factory()
            ->count(5)
            ->online()
            ->sequence(
                ['transaction_date' => '2026-02-02', 'online_amount' => 5000000],
                ['transaction_date' => '2026-02-04', 'online_amount' => 3000000],
                ['transaction_date' => '2026-02-06', 'online_amount' => 7000000],
                ['transaction_date' => '2026-02-09', 'online_amount' => 6000000],
                ['transaction_date' => '2026-02-13', 'online_amount' => 5000000],
            )
            ->create(['supplier_id' => $nestle->id]);

        LegerRegister::factory()
            ->count(5)
            ->invoice()
            ->sequence(
                ['transaction_date' => '2026-02-04', 'document_number' => '1073527835', 'invoice_amount' => 20421.54, 'za_point_five_percent_amount' => 102.11],
                ['transaction_date' => '2026-02-04', 'document_number' => '1073527810', 'invoice_amount' => 11199451.37, 'za_point_five_percent_amount' => 55997.26],
                ['transaction_date' => '2026-02-05', 'document_number' => '1073528188', 'invoice_amount' => 4917548.59, 'za_point_five_percent_amount' => 24587.74],
                ['transaction_date' => '2026-02-07', 'document_number' => '1073528354', 'invoice_amount' => 449711.60, 'za_point_five_percent_amount' => 2248.56],
                ['transaction_date' => '2026-02-09', 'document_number' => '1073528638', 'invoice_amount' => 897504.70, 'za_point_five_percent_amount' => 4487.52],
            )
            ->create(['supplier_id' => $nestle->id]);

        LegerRegister::factory()
            ->count(3)
            ->claim()
            ->sequence(
                ['transaction_date' => '2026-02-20', 'document_number' => 'ST-26-11', 'claim_adjust_amount' => 217769],
                ['transaction_date' => '2026-02-20', 'document_number' => 'ST-26-10', 'claim_adjust_amount' => 139500],
                ['transaction_date' => '2026-02-20', 'document_number' => 'ST-26-8', 'claim_adjust_amount' => 139500],
            )
            ->create(['supplier_id' => $nestle->id]);

        // Recalculate balances
        LegerRegister::recalculateBalances($nestle->id);
    }
}
