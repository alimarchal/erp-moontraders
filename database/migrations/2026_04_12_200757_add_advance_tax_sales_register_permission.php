<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->insertOrIgnore([
            'name' => 'report-audit-advance-tax-sales-register',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissionId = DB::table('permissions')
            ->where('name', 'report-audit-advance-tax-sales-register')
            ->where('guard_name', 'web')
            ->value('id');

        $roleNames = ['super-admin', 'admin', 'inventory-manager'];
        $roleIds = DB::table('roles')
            ->whereIn('name', $roleNames)
            ->where('guard_name', 'web')
            ->pluck('id');

        $pivotRows = $roleIds->map(fn ($roleId) => [
            'permission_id' => $permissionId,
            'role_id' => $roleId,
        ])->all();

        DB::table('role_has_permissions')->insertOrIgnore($pivotRows);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'report-audit-advance-tax-sales-register')
            ->where('guard_name', 'web')
            ->value('id');

        if ($permissionId) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
