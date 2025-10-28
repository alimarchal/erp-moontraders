<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AccountTypeSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        DB::table('account_types')->insert([
            [
                'id' => 1,
                'type_name' => 'Asset',
                'report_group' => 'BalanceSheet',
                'description' => 'What your business owns (e.g., cash, equipment, inventory).',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 2,
                'type_name' => 'Liability',
                'report_group' => 'BalanceSheet',
                'description' => 'What your business owes to others (e.g., loans, accounts payable).',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 3,
                'type_name' => 'Equity',
                'report_group' => 'BalanceSheet',
                'description' => 'The net worth of the company.',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 4,
                'type_name' => 'Revenue',
                'report_group' => 'IncomeStatement',
                'description' => 'Money earned from sales and services.',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id' => 5,
                'type_name' => 'Expense',
                'report_group' => 'IncomeStatement',
                'description' => 'Costs incurred to operate the business (e.g., rent, salaries).',
                'created_at' => $now,
                'updated_at' => $now
            ],
        ]);
    }
}
