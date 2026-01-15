<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Define groups and their permissions
        $permissions = [
            'user' => ['list', 'create', 'edit', 'delete', 'bulk-update'],
            'role' => ['list', 'create', 'edit', 'delete', 'sync'],
            'permission' => ['list', 'create', 'edit', 'delete'],
            'accounting' => ['view', 'manage', 'post', 'reverse'],
            'accounting-period' => ['list', 'create', 'edit', 'delete', 'close', 'open'],
            'chart-of-account' => ['list', 'create', 'edit', 'delete'],
            'journal-entry' => ['list', 'create', 'edit', 'delete', 'post', 'reverse'],
            'supplier' => ['list', 'create', 'edit', 'delete'],
            'customer' => ['list', 'create', 'edit', 'delete'],
            'inventory' => ['view', 'manage', 'adjust'],
            'report' => ['view-financial', 'view-inventory', 'view-audit'],
            'setting' => ['view', 'update'],
        ];

        $allPermissionNames = [];
        foreach ($permissions as $group => $actions) {
            foreach ($actions as $action) {
                $name = "{$group}-{$action}";
                Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
                $allPermissionNames[] = $name;
            }
        }

        // Create roles and assign permissions

        // Super Admin: Gets everything
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $superAdminRole->syncPermissions(Permission::all());

        // Admin: Most things
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions(array_filter($allPermissionNames, function($name) {
            return !str_contains($name, 'delete') || str_contains($name, 'journal-entry');
        }));

        // Accountant
        $accountantRole = Role::firstOrCreate(['name' => 'accountant', 'guard_name' => 'web']);
        $accountantRole->syncPermissions([
            'accounting-view', 'accounting-manage', 'accounting-post',
            'accounting-period-list', 'accounting-period-open',
            'chart-of-account-list', 'chart-of-account-create', 'chart-of-account-edit',
            'journal-entry-list', 'journal-entry-create', 'journal-entry-edit', 'journal-entry-post',
            'report-view-financial',
        ]);

        // User
        $userRole = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        $userRole->syncPermissions([
            'user-list',
            'report-view-financial',
        ]);

        // Inventory Manager
        $inventoryManagerRole = Role::firstOrCreate(['name' => 'inventory-manager', 'guard_name' => 'web']);
        $inventoryManagerRole->syncPermissions([
            'inventory-view', 'inventory-manage', 'inventory-adjust',
            'report-view-inventory',
            'supplier-list', 'supplier-create', 'supplier-edit',
            'customer-list', 'customer-create', 'customer-edit',
        ]);
    }
}
