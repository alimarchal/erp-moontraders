<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $dataPath = database_path('seeders/data/employee_list.json');

        if (!file_exists($dataPath)) {
            $this->command?->warn('Employee data file not found, skipping employee seed.');
            return;
        }

        $records = json_decode(file_get_contents($dataPath), true);

        if (!is_array($records) || $records === []) {
            $this->command?->warn('Employee data file is empty, skipping employee seed.');
            return;
        }

        foreach ($records as $row) {
            $serial = $row['serial'] ?? null;
            $name = $row['name'] ?? null;

            if ($serial === null || $name === null) {
                continue;
            }

            $code = sprintf('EMP%04d', (int) $serial);

            // Prevent duplicates when seeding multiple times
            if (Employee::where('employee_code', $code)->exists()) {
                continue;
            }

            $phone = isset($row['phone']) ? trim((string) $row['phone']) : null;
            $phone = $phone === '' ? null : preg_replace('/\\s+/', ' ', $phone);

            Employee::create([
                'company_id' => $row['company_id'] ?? null,
                'supplier_id' => $row['supplier_id'] ?? null,
                'employee_code' => $code,
                'name' => $name,
                'company_name' => $row['company'] ?? null,
                'designation' => $row['designation'] ?? null,
                'phone' => $phone,
                'email' => null,
                'address' => null,
                'warehouse_id' => null,
                'user_id' => null,
                'hire_date' => null,
                'is_active' => true,
            ]);
        }
    }
}
