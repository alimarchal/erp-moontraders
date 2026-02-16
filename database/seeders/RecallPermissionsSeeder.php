<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RecallPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'stock-adjustment-list',
            'stock-adjustment-create',
            'stock-adjustment-edit',
            'stock-adjustment-delete',
            'stock-adjustment-post',

            'product-recall-list',
            'product-recall-create',
            'product-recall-edit',
            'product-recall-delete',
            'product-recall-post',
            'product-recall-cancel',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $this->command->info('Stock adjustment and product recall permissions created');

        $superAdminRole = Role::where('name', 'Super Admin')->first();
        if ($superAdminRole) {
            $superAdminRole->givePermissionTo($permissions);
            $this->command->info('Permissions assigned to Super Admin role');
        }

        $warehouseManagerRole = Role::where('name', 'Warehouse Manager')->first();
        if ($warehouseManagerRole) {
            $warehouseManagerRole->givePermissionTo([
                'stock-adjustment-list',
                'stock-adjustment-create',
                'stock-adjustment-edit',
                'product-recall-list',
                'product-recall-create',
                'product-recall-edit',
            ]);
            $this->command->info('Permissions assigned to Warehouse Manager role');
        }
    }
}
