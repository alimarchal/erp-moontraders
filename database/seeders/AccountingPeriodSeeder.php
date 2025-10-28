<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AccountingPeriodSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        DB::table('accounting_periods')->insert([
            [
                'name' => 'Fiscal Year 2024',
                'start_date' => '2024-01-01',
                'end_date' => '2024-12-31',
                'status' => 'closed',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'name' => 'Fiscal Year 2025',
                'start_date' => '2025-01-01',
                'end_date' => '2025-12-31',
                'status' => 'open',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'name' => 'Q1 2025',
                'start_date' => '2025-01-01',
                'end_date' => '2025-03-31',
                'status' => 'closed',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'name' => 'Q2 2025',
                'start_date' => '2025-04-01',
                'end_date' => '2025-06-30',
                'status' => 'closed',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'name' => 'Q3 2025',
                'start_date' => '2025-07-01',
                'end_date' => '2025-09-30',
                'status' => 'closed',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'name' => 'Q4 2025',
                'start_date' => '2025-10-01',
                'end_date' => '2025-12-31',
                'status' => 'open',
                'created_at' => $now,
                'updated_at' => $now
            ],
        ]);
    }
}
