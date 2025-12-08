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
        // ======================
        // CORE DEPARTMENTS
        // ======================

        CostCenter::create([
            'code' => 'CC001',
            'name' => 'Finance & Accounting',
            'description' => 'Accounting, bookkeeping, financial management, cashiers, and treasury',
            'type' => 'cost_center',
            'is_active' => true,
        ]);

        CostCenter::create([
            'code' => 'CC002',
            'name' => 'Human Resources',
            'description' => 'HR management, payroll, recruitment, and employee relations',
            'type' => 'cost_center',
            'is_active' => true,
        ]);

        CostCenter::create([
            'code' => 'CC003',
            'name' => 'Administration & Management',
            'description' => 'General management, supervisors, and administrative operations',
            'type' => 'cost_center',
            'is_active' => true,
        ]);

        // ======================
        // OPERATIONAL DEPARTMENTS
        // ======================

        CostCenter::create([
            'code' => 'CC004',
            'name' => 'Sales & Marketing',
            'description' => 'Sales team, marketing, and business development',
            'type' => 'cost_center',
            'is_active' => true,
        ]);

        CostCenter::create([
            'code' => 'CC005',
            'name' => 'Field Sales Operations',
            'description' => 'DSR, FSO, Order Bookers, salesmen, and field staff',
            'type' => 'cost_center',
            'is_active' => true,
        ]);

        CostCenter::create([
            'code' => 'CC006',
            'name' => 'Warehouse & Inventory',
            'description' => 'Store keepers, loaders, warehouse incharge, and inventory management',
            'type' => 'cost_center',
            'is_active' => true,
        ]);

        CostCenter::create([
            'code' => 'CC007',
            'name' => 'Transport & Logistics',
            'description' => 'Drivers, delivery staff, helpers, and vehicle operations',
            'type' => 'cost_center',
            'is_active' => true,
        ]);

        CostCenter::create([
            'code' => 'CC008',
            'name' => 'IT & Systems',
            'description' => 'Information technology, ERP support, and technical operations',
            'type' => 'cost_center',
            'is_active' => true,
        ]);

        CostCenter::create([
            'code' => 'CC009',
            'name' => 'Legal & Compliance',
            'description' => 'Legal affairs, contracts, regulatory compliance, and documentation',
            'type' => 'cost_center',
            'is_active' => true,
        ]);

        CostCenter::create([
            'code' => 'CC010',
            'name' => 'Procurement & Purchasing',
            'description' => 'Supplier management, purchasing, and procurement operations',
            'type' => 'cost_center',
            'is_active' => true,
        ]);

        // ======================
        // SUPPORT SERVICES
        // ======================

        CostCenter::create([
            'code' => 'CC011',
            'name' => 'Security Services',
            'description' => 'Security guards and premises protection',
            'type' => 'cost_center',
            'is_active' => true,
        ]);

        CostCenter::create([
            'code' => 'CC012',
            'name' => 'Facility Services',
            'description' => 'Kitchen staff, cooks, sweepers, cleaning, and facility maintenance',
            'type' => 'cost_center',
            'is_active' => true,
        ]);

        // ======================
        // LOCATION
        // ======================

        CostCenter::create([
            'code' => 'LOC001',
            'name' => 'MOON TRADERS - Gojra',
            'description' => 'Main office - Gojra, Muzaffarabad, Azad Jammu and Kashmir',
            'type' => 'cost_center',
            'is_active' => true,
        ]);
    }
}
