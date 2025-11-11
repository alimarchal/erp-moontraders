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
        // OPERATIONAL COST CENTERS
        // ======================

        CostCenter::create([
            'code' => 'CC001',
            'name' => 'Accounting Department',
            'description' => 'General accounting, bookkeeping, and financial management',
            'type' => 'cost_center',
            'is_active' => true,
        ]);

        CostCenter::create([
            'code' => 'CC002',
            'name' => 'Sales & Field Operations',
            'description' => 'Sales team, DSR, FSO, Order Bookers, and field sales staff',
            'type' => 'cost_center',
            'is_active' => true,
        ]);

        CostCenter::create([
            'code' => 'CC003',
            'name' => 'Warehouse & Inventory',
            'description' => 'Store keepers, loaders, warehouse incharge, and inventory management',
            'type' => 'cost_center',
            'is_active' => true,
        ]);

        CostCenter::create([
            'code' => 'CC004',
            'name' => 'Transport & Delivery',
            'description' => 'Drivers, delivery staff, helpers, and vehicle operations',
            'type' => 'cost_center',
            'is_active' => true,
        ]);

        CostCenter::create([
            'code' => 'CC005',
            'name' => 'Support Services',
            'description' => 'Security guards, cooks, sweepers, and general support staff',
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