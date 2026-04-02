<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExpenseDetailSeeder extends Seeder
{
    public function run(): void
    {
        // Skip if required FK data is not seeded (production-only data seeder).
        $nestleExists = DB::table('suppliers')->where('short_name', 'Nestle')->exists();
        if (! $nestleExists || ! DB::table('users')->where('id', 1)->exists()) {
            return;
        }

        $rows = [
            0 => [
                'id' => 1,
                'category' => 'stationary',
                'supplier_id' => 3,
                'transaction_date' => '2026-03-31',
                'description' => 'Stationary/TCS',
                'amount' => '13710.00',
                'vehicle_id' => null,
                'vehicle_type' => null,
                'driver_employee_id' => null,
                'liters' => null,
                'employee_id' => null,
                'employee_no' => null,
                'debit' => '13710.00',
                'credit' => '0.00',
                'debit_account_id' => 64,
                'credit_account_id' => 7,
                'notes' => null,
                'journal_entry_id' => null,
                'posted_at' => null,
                'posted_by' => null,
                'created_by' => 1,
                'updated_by' => 1,
                'deleted_at' => null,
                'created_at' => '2026-03-31 17:00:00',
                'updated_at' => '2026-03-31 17:00:00',
            ],
            1 => [
                'id' => 2,
                'category' => 'tonner_it',
                'supplier_id' => 3,
                'transaction_date' => '2026-03-31',
                'description' => 'Toner/IT',
                'amount' => '4200.00',
                'vehicle_id' => null,
                'vehicle_type' => null,
                'driver_employee_id' => null,
                'liters' => null,
                'employee_id' => null,
                'employee_no' => null,
                'debit' => '4200.00',
                'credit' => '0.00',
                'debit_account_id' => 112,
                'credit_account_id' => 7,
                'notes' => null,
                'journal_entry_id' => null,
                'posted_at' => null,
                'posted_by' => null,
                'created_by' => 1,
                'updated_by' => 1,
                'deleted_at' => null,
                'created_at' => '2026-03-31 17:00:00',
                'updated_at' => '2026-03-31 17:00:00',
            ],
            2 => [
                'id' => 3,
                'category' => 'fuel',
                'supplier_id' => 3,
                'transaction_date' => '2026-03-31',
                'description' => 'Diseal',
                'amount' => '422862.00',
                'vehicle_id' => null,
                'vehicle_type' => null,
                'driver_employee_id' => null,
                'liters' => null,
                'employee_id' => null,
                'employee_no' => null,
                'debit' => '422862.00',
                'credit' => '0.00',
                'debit_account_id' => 113,
                'credit_account_id' => 7,
                'notes' => null,
                'journal_entry_id' => null,
                'posted_at' => null,
                'posted_by' => null,
                'created_by' => 1,
                'updated_by' => 1,
                'deleted_at' => null,
                'created_at' => '2026-03-31 17:00:00',
                'updated_at' => '2026-03-31 17:00:00',
            ],
            3 => [
                'id' => 4,
                'category' => 'salaries',
                'supplier_id' => 3,
                'transaction_date' => '2026-03-31',
                'description' => 'Salaries',
                'amount' => '1779200.00',
                'vehicle_id' => null,
                'vehicle_type' => null,
                'driver_employee_id' => null,
                'liters' => null,
                'employee_id' => null,
                'employee_no' => null,
                'debit' => '1779200.00',
                'credit' => '0.00',
                'debit_account_id' => 66,
                'credit_account_id' => 7,
                'notes' => null,
                'journal_entry_id' => null,
                'posted_at' => null,
                'posted_by' => null,
                'created_by' => 1,
                'updated_by' => 1,
                'deleted_at' => null,
                'created_at' => '2026-03-31 17:00:00',
                'updated_at' => '2026-03-31 17:00:00',
            ],
            4 => [
                'id' => 5,
                'category' => 'van_work',
                'supplier_id' => 3,
                'transaction_date' => '2026-03-31',
                'description' => 'Van Work',
                'amount' => '39080.00',
                'vehicle_id' => null,
                'vehicle_type' => null,
                'driver_employee_id' => null,
                'liters' => null,
                'employee_id' => null,
                'employee_no' => null,
                'debit' => '39080.00',
                'credit' => '0.00',
                'debit_account_id' => 114,
                'credit_account_id' => 7,
                'notes' => null,
                'journal_entry_id' => null,
                'posted_at' => null,
                'posted_by' => null,
                'created_by' => 1,
                'updated_by' => 1,
                'deleted_at' => null,
                'created_at' => '2026-03-31 17:00:00',
                'updated_at' => '2026-03-31 17:00:00',
            ],
        ];

        DB::table('expense_details')->upsert($rows, ['id'], [
            'id',
            'category',
            'supplier_id',
            'transaction_date',
            'description',
            'amount',
            'vehicle_id',
            'vehicle_type',
            'driver_employee_id',
            'liters',
            'employee_id',
            'employee_no',
            'debit',
            'credit',
            'debit_account_id',
            'credit_account_id',
            'notes',
            'journal_entry_id',
            'posted_at',
            'posted_by',
            'created_by',
            'updated_by',
            'deleted_at',
            'created_at',
            'updated_at',
        ]);

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("SELECT setval('expense_details_id_seq', COALESCE(MAX(id), 1)) FROM expense_details");
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            $nextId = (int) DB::table('expense_details')->max('id') + 1;
            if ($nextId < 1) {
                $nextId = 1;
            }
            DB::statement("ALTER TABLE expense_details AUTO_INCREMENT = {$nextId}");
        }
    }
}
