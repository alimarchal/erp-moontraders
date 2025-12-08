<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Warehouse;
use App\Models\WarehouseType;
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
        $coldStorageType = WarehouseType::where('name', 'Cold Storage')->first();
        $rejectedType = WarehouseType::where('name', 'Rejected')->first();

        // Get an asset account for warehouses
        $assetAccount = ChartOfAccount::where('is_active', true)
            ->whereHas('accountType', function ($query) {
                $query->where('type_name', 'Asset');
            })
            ->first();

        $warehouses = [
            [
                'warehouse_name' => 'Warehouse - I',
                'disabled' => false,
                'is_group' => false,
                'company_id' => $company?->id,
                'warehouse_type_id' => $generalType?->id,
                'is_rejected_warehouse' => false,
                'account_id' => $assetAccount?->id,
                'email_id' => 'warehouse1@moontraders.com',
                'phone_no' => '+92-51-1234567',
                'mobile_no' => '+92-300-1234567',
                'address_line_1' => 'Main Storage Facility',
                'city' => 'Rawalpindi',
                'state' => 'Punjab',
                'pin' => '46000',
            ],
            [
                'warehouse_name' => 'Warehouse - II',
                'disabled' => false,
                'is_group' => false,
                'company_id' => $company?->id,
                'warehouse_type_id' => $generalType?->id,
                'is_rejected_warehouse' => false,
                'account_id' => $assetAccount?->id,
                'email_id' => 'warehouse2@moontraders.com',
                'phone_no' => '+92-51-7654321',
                'mobile_no' => '+92-300-7654321',
                'address_line_1' => 'Secondary Storage Facility',
                'city' => 'Rawalpindi',
                'state' => 'Punjab',
                'pin' => '46000',
            ],
            [
                'warehouse_name' => 'Cold Storage',
                'disabled' => false,
                'is_group' => false,
                'company_id' => $company?->id,
                'warehouse_type_id' => $coldStorageType?->id,
                'is_rejected_warehouse' => false,
                'account_id' => $assetAccount?->id,
                'email_id' => 'coldstorage@moontraders.com',
                'phone_no' => '+92-51-2345678',
                'mobile_no' => '+92-300-2345678',
                'address_line_1' => 'Refrigerated Storage Unit',
                'city' => 'Rawalpindi',
                'state' => 'Punjab',
                'pin' => '46000',
            ],
            [
                'warehouse_name' => 'Rejected',
                'disabled' => false,
                'is_group' => false,
                'company_id' => $company?->id,
                'warehouse_type_id' => $rejectedType?->id,
                'is_rejected_warehouse' => true,
                'account_id' => $assetAccount?->id,
                'email_id' => 'rejected@moontraders.com',
                'phone_no' => '+92-51-3456789',
                'mobile_no' => '+92-300-3456789',
                'address_line_1' => 'Rejected Items Warehouse',
                'city' => 'Rawalpindi',
                'state' => 'Punjab',
                'pin' => '46000',
            ],
            [
                'warehouse_name' => 'Transit',
                'disabled' => false,
                'is_group' => false,
                'company_id' => $company?->id,
                'warehouse_type_id' => $transitType?->id,
                'is_rejected_warehouse' => false,
                'account_id' => $assetAccount?->id,
                'email_id' => 'transit@moontraders.com',
                'phone_no' => '+92-51-4567890',
                'mobile_no' => '+92-300-4567890',
                'address_line_1' => 'Transit Warehouse',
                'city' => 'Rawalpindi',
                'state' => 'Punjab',
                'pin' => '46000',
            ],
        ];

        foreach ($warehouses as $warehouse) {
            Warehouse::create($warehouse);
        }
    }
}
