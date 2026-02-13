<?php

namespace Database\Seeders;

use App\Models\EmployeeSalaryTransaction;
use Illuminate\Database\Seeder;

class EmployeeSalaryTransactionSeeder extends Seeder
{
    public function run(): void
    {
        EmployeeSalaryTransaction::factory()->count(10)->salary()->pending()->create();
        EmployeeSalaryTransaction::factory()->count(5)->advance()->pending()->create();
        EmployeeSalaryTransaction::factory()->count(3)->advanceRecovery()->pending()->create();
    }
}
