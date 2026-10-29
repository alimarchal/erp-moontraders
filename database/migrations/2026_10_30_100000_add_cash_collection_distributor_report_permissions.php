<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = [
        'report-audit-cash-collection-distributor',
        'report-audit-cash-collection-distributor-all-companies',
    ];

    /**
     * Created here so production only needs `php artisan migrate`; super-admin and admin receive them.
     */
    public function up(): void
    {
        $now = now();

        DB::table('permissions')->insertOrIgnore(array_map(fn ($name) => [
            'name' => $name,
            'guard_name' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ], self::PERMISSIONS));

        $permissionIds = DB::table('permissions')->whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->pluck('id');
        $roleIds = DB::table('roles')->whereIn('name', ['super-admin', 'admin'])->where('guard_name', 'web')->pluck('id');

        $pivotRows = $roleIds->flatMap(fn ($roleId) => $permissionIds->map(fn ($permissionId) => [
            'permission_id' => $permissionId,
            'role_id' => $roleId,
        ]))->all();

        if ($pivotRows !== []) {
            DB::table('role_has_permissions')->insertOrIgnore($pivotRows);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
