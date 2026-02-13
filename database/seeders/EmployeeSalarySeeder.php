<?php

namespace Database\Seeders;

use App\Models\EmployeeSalary;
use Illuminate\Database\Seeder;

class EmployeeSalarySeeder extends Seeder
{
    public function run(): void
    {
        EmployeeSalary::factory()->count(10)->active()->create();
        EmployeeSalary::factory()->count(3)->inactive()->create();
    }
}
