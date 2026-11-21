<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /** @var array<int, string> */
    private const PERMISSIONS = [
        'report-audit-daily-business-summary',
        'report-audit-daily-business-summary-all-companies',
    ];

    /**
     * Created here so production only needs `php artisan migrate`; super-admin and admin receive both.
     */
    public function up(): void
    {
        $now = now();

        DB::table('permissions')->insertOrIgnore(array_map(fn (string $name) => [
            'name' => $name,
            'guard_name' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ], self::PERMISSIONS));

        $permissionIds = DB::table('permissions')
            ->whereIn('name', self::PERMISSIONS)
            ->where('guard_name', 'web')
            ->pluck('id');

        $roleIds = DB::table('roles')
            ->whereIn('name', ['super-admin', 'admin'])
            ->where('guard_name', 'web')
            ->pluck('id');

        $pivotRows = $roleIds->crossJoin($permissionIds)
            ->map(fn (array $pair) => ['role_id' => $pair[0], 'permission_id' => $pair[1]])
            ->all();

        if ($pivotRows !== []) {
            DB::table('role_has_permissions')->insertOrIgnore($pivotRows);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', self::PERMISSIONS)
            ->where('guard_name', 'web')
            ->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
