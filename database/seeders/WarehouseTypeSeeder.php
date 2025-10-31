<?php

namespace Database\Seeders;

use App\Models\WarehouseType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WarehouseTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            [
                'name' => 'General',
                'description' => 'General purpose warehouse for regular inventory',
                'is_active' => true,
            ],
            [
                'name' => 'Transit',
                'description' => 'Warehouse for goods in transit',
                'is_active' => true,
            ],
            [
                'name' => 'Rejected',
                'description' => 'Warehouse for rejected or defective items',
                'is_active' => true,
            ],
            [
                'name' => 'Cold Storage',
                'description' => 'Temperature-controlled warehouse',
                'is_active' => true,
            ],
        ];

        foreach ($types as $type) {
            WarehouseType::create($type);
        }
    }
}
