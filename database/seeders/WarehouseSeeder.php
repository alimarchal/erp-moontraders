<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use App\Models\WarehouseType;
use App\Models\Company;
use App\Models\ChartOfAccount;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first company and warehouse type
        $company = Company::first();
        $generalType = WarehouseType::where('name', 'General')->first();
        $transitType = WarehouseType::where('name', 'Transit')->first();

        // Get an asset account for warehouses
        $assetAccount = ChartOfAccount::where('is_active', true)
            ->whereHas('accountType', function ($query) {
                $query->where('type_name', 'Asset');
            })
            ->first();

        $warehouses = [
            [
                'warehouse_name' => 'Main Warehouse',
                'disabled' => false,
                'is_group' => false,
                'company_id' => $company?->id,
                'warehouse_type_id' => $generalType?->id,
                'is_rejected_warehouse' => false,
                'account_id' => $assetAccount?->id,
                'email_id' => 'main.warehouse@example.com',
                'phone_no' => '+1234567890',
                'mobile_no' => '+1234567891',
                'address_line_1' => '123 Industrial Avenue',
                'address_line_2' => 'Building A',
                'city' => 'Karachi',
                'state' => 'Sindh',
                'pin' => '75300',
            ],
            [
                'warehouse_name' => 'Distribution Center',
                'disabled' => false,
                'is_group' => false,
                'company_id' => $company?->id,
                'warehouse_type_id' => $generalType?->id,
                'is_rejected_warehouse' => false,
                'account_id' => $assetAccount?->id,
                'email_id' => 'dc.warehouse@example.com',
                'phone_no' => '+1234567892',
                'mobile_no' => '+1234567893',
                'address_line_1' => '456 Logistics Street',
                'address_line_2' => 'Suite 200',
                'city' => 'Lahore',
                'state' => 'Punjab',
                'pin' => '54000',
            ],
            [
                'warehouse_name' => 'Transit Warehouse',
                'disabled' => false,
                'is_group' => false,
                'company_id' => $company?->id,
                'warehouse_type_id' => $transitType?->id,
                'is_rejected_warehouse' => false,
                'account_id' => $assetAccount?->id,
                'email_id' => 'transit@example.com',
                'phone_no' => '+1234567894',
                'city' => 'Islamabad',
                'state' => 'ICT',
                'pin' => '44000',
            ],
        ];

        foreach ($warehouses as $warehouse) {
            Warehouse::create($warehouse);
        }
    }
}
