<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\CostCenter;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    /**
     * Designation to Cost Center mapping
     */
    protected function getDesignationCostCenterMap(): array
    {
        return [
            // CC001: Finance & Accounting
            'Accountant' => 1,
            'Account Officer' => 1,
            'Accounts' => 1,
            'Cashier' => 1,
            'KPO' => 1,
            'Treasury Officer' => 1,

            // CC002: Human Resources
            'HR Manager' => 2,
            'HR Officer' => 2,
            'Payroll Officer' => 2,

            // CC003: Administration & Management
            'Admin' => 3,
            'Admin Officer' => 3,
            'General Manager' => 3,
            'Manager' => 3,
            'Office Boy' => 3,
            'Proprietor' => 3,
            'Supervisor' => 3,

            // CC004: Sales & Marketing
            'Salesman' => 4,
            'Sales Officer' => 4,
            'Sales Executive' => 4,

            // CC005: Field Sales Operations
            'DSR' => 5,
            'FSO' => 5,
            'Order Booker' => 5,

            // CC006: Warehouse & Inventory
            'Helper' => 6,
            'Loader' => 6,
            'Store Incharge' => 6,
            'Store Keeper' => 6,
            'WH Incharge' => 6,

            // CC007: Transport & Logistics
            'Delivery Boy' => 7,
            'Driver' => 7,

            // CC008: IT & Systems
            'IT Support' => 8,
            'System Administrator' => 8,

            // CC009: Legal & Compliance
            'Legal Officer' => 9,
            'Compliance Officer' => 9,

            // CC010: Procurement & Purchasing
            'Purchase Officer' => 10,
            'Procurement Manager' => 10,

            // CC011: Security Services
            'Chokidar' => 11,
            'Security Guard' => 11,

            // CC012: Facility Services
            'Cook' => 12,
            'Sweeper' => 12,
        ];
    }

    /**
     * Get cost center ID for a designation
     */
    protected function getCostCenterForDesignation(?string $designation): ?int
    {
        if (!$designation) {
            return null;
        }

        $map = $this->getDesignationCostCenterMap();

        // Exact match
        if (isset($map[$designation])) {
            return $map[$designation];
        }

        // Fuzzy match (case-insensitive, partial)
        $designationLower = strtolower(trim($designation));
        foreach ($map as $key => $costCenterId) {
            if (
                str_contains($designationLower, strtolower($key)) ||
                str_contains(strtolower($key), $designationLower)
            ) {
                return $costCenterId;
            }
        }

        // Default to Administration if no match
        return 3; // CC003: Administration & Management
    }

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

            $designation = $row['designation'] ?? null;
            $costCenterId = $this->getCostCenterForDesignation($designation);

            Employee::create([
                'company_id' => $row['company_id'] ?? null,
                'supplier_id' => $row['supplier_id'] ?? null,
                'employee_code' => $code,
                'name' => $name,
                'company_name' => $row['company'] ?? null,
                'designation' => $designation,
                'phone' => $phone,
                'email' => null,
                'address' => null,
                'warehouse_id' => $row['warehouse_id'] ?? null,
                'cost_center_id' => $costCenterId,
                'user_id' => null,
                'hire_date' => null,
                'is_active' => true,
            ]);
        }
    }
}
