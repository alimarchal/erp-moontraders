<?php

namespace Database\Seeders;

use App\Models\CostCenter;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CostCenterSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create cost centers (departments)
        CostCenter::create([
            'code' => 'CC001',
            'name' => 'Sales Department',
            'description' => 'Sales and marketing operations',
            'type' => 'cost_center',
            'is_active' => true,
        ]);

        CostCenter::create([
            'code' => 'CC002',
            'name' => 'IT Department',
            'description' => 'Information technology and systems',
            'type' => 'cost_center',
            'is_active' => true,
        ]);

        CostCenter::create([
            'code' => 'CC003',
            'name' => 'HR Department',
            'description' => 'Human resources and administration',
            'type' => 'cost_center',
            'is_active' => true,
        ]);

        CostCenter::create([
            'code' => 'CC004',
            'name' => 'Finance Department',
            'description' => 'Finance and accounting operations',
            'type' => 'cost_center',
            'is_active' => true,
        ]);

        // Create projects
        CostCenter::create([
            'code' => 'PROJ001',
            'name' => 'Website Redesign 2025',
            'description' => 'Company website redesign project',
            'type' => 'project',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'is_active' => true,
        ]);

        CostCenter::create([
            'code' => 'PROJ002',
            'name' => 'ERP Implementation',
            'description' => 'Enterprise resource planning system implementation',
            'type' => 'project',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'is_active' => true,
        ]);
    }
}
