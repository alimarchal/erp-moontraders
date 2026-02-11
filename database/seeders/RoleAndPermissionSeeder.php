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
            // Access Control
            'user' => ['list', 'create', 'edit', 'delete', 'bulk-update'],
            'role' => ['list', 'create', 'edit', 'delete', 'sync'],
            'permission' => ['list', 'create', 'edit', 'delete'],

            // Accounting
            'accounting' => ['view', 'manage', 'post', 'reverse'],
            'accounting-period' => ['list', 'create', 'edit', 'delete', 'close', 'open'],
            'chart-of-account' => ['list', 'create', 'edit', 'delete'],
            'journal-entry' => ['list', 'create', 'edit', 'delete', 'post', 'reverse'],
            'account-type' => ['list', 'create', 'edit', 'delete'],
            'currency' => ['list', 'create', 'edit', 'delete'],
            'cost-center' => ['list', 'create', 'edit', 'delete'],
            'bank-account' => ['list', 'create', 'edit', 'delete'],
            'tax' => ['list', 'create', 'edit', 'delete', 'manage-mapping'],

            // Business Entities
            'company' => ['list', 'create', 'edit', 'delete'],
            'supplier' => ['list', 'create', 'edit', 'delete'],
            'customer' => ['list', 'create', 'edit', 'delete'],
            'employee' => ['list', 'create', 'edit', 'delete'],

            // Inventory & Production
            'goods-receipt-note' => ['list', 'create', 'edit', 'delete', 'post', 'reverse'],
            'goods-issue' => ['list', 'create', 'edit', 'delete', 'post'],
            'stock-transfer' => ['list', 'create', 'edit', 'delete', 'post'],
            'stock-adjustment' => ['list', 'create', 'edit', 'delete', 'post'],
            'warehouse' => ['list', 'create', 'edit', 'delete'],
            'warehouse-type' => ['list', 'create', 'edit', 'delete'],
            'product' => ['list', 'create', 'edit', 'delete'],
            'category' => ['list', 'create', 'edit', 'delete'],

            'uom' => ['list', 'create', 'edit', 'delete'],

            // Sales & Distribution
            'sales-settlement' => ['list', 'create', 'edit', 'delete', 'post'],
            'supplier-payment' => ['list', 'create', 'edit', 'delete', 'post'],
            'vehicle' => ['list', 'create', 'edit', 'delete'],
            'promotional-campaign' => ['list', 'create', 'edit', 'delete'],

            // Reports & Utilities
            'report' => ['view-financial', 'view-inventory', 'view-sales', 'view-audit'],
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
        $adminRole->syncPermissions(array_filter($allPermissionNames, function ($name) {
            return ! str_contains($name, 'delete') || str_contains($name, 'journal-entry');
        }));

        // Accountant
        $accountantRole = Role::firstOrCreate(['name' => 'accountant', 'guard_name' => 'web']);
        $accountantRole->syncPermissions([
            'accounting-view',
            'accounting-manage',
            'accounting-post',
            'accounting-period-list',
            'accounting-period-open',
            'chart-of-account-list',
            'chart-of-account-create',
            'chart-of-account-edit',
            'journal-entry-list',
            'journal-entry-create',
            'journal-entry-edit',
            'journal-entry-post',
            'account-type-list',
            'currency-list',
            'cost-center-list',
            'bank-account-list',
            'tax-list',
            'supplier-list',
            'customer-list',
            'supplier-payment-list',
            'supplier-payment-create',
            'supplier-payment-post',
            'report-view-financial',
        ]);

        // Inventory Manager
        $inventoryManagerRole = Role::firstOrCreate(['name' => 'inventory-manager', 'guard_name' => 'web']);
        $inventoryManagerRole->syncPermissions([
            'goods-receipt-note-list',
            'goods-receipt-note-create',
            'goods-receipt-note-edit',
            'goods-receipt-note-post',
            'goods-issue-list',
            'goods-issue-create',
            'goods-issue-edit',
            'goods-issue-post',
            'stock-transfer-list',
            'stock-transfer-create',
            'stock-transfer-edit',
            'stock-transfer-post',
            'stock-adjustment-list',
            'stock-adjustment-create',
            'stock-adjustment-post',
            'warehouse-list',
            'product-list',
            'uom-list',
            'report-view-inventory',
            'supplier-list',
            'customer-list',
        ]);

        // Sales Manager
        $salesManagerRole = Role::firstOrCreate(['name' => 'sales-manager', 'guard_name' => 'web']);
        $salesManagerRole->syncPermissions([
            'sales-settlement-list',
            'sales-settlement-create',
            'sales-settlement-edit',
            'sales-settlement-post',
            'customer-list',
            'customer-create',
            'customer-edit',
            'vehicle-list',
            'promotional-campaign-list',
            'report-view-sales',
        ]);

        // User
        $userRole = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        $userRole->syncPermissions([
            'report-view-sales',
            'report-view-inventory',
        ]);
    }
}
