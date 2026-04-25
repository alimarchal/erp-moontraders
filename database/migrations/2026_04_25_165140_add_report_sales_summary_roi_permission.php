<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $guardName = 'web';
        $now = now();

        DB::table('permissions')->insertOrIgnore([
            ['name' => 'report-sales-summary-roi', 'guard_name' => $guardName, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $permissionId = DB::table('permissions')
            ->where('name', 'report-sales-summary-roi')
            ->where('guard_name', $guardName)
            ->value('id');

        $roleIds = DB::table('roles')
            ->whereIn('name', ['super-admin', 'admin', 'inventory-manager'])
            ->where('guard_name', $guardName)
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
            ->where('name', 'report-sales-summary-roi')
            ->where('guard_name', 'web')
            ->value('id');

        if ($permissionId) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
