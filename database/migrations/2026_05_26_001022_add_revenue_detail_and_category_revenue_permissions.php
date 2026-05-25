<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $guardName = 'web';
        $now = now();

        $permissions = [
            'category-revenue-list',
            'category-revenue-create',
            'category-revenue-edit',
            'category-revenue-delete',
            'report-audit-revenue-detail',
            'revenue-detail-create',
            'revenue-detail-edit',
            'revenue-detail-delete',
            'revenue-detail-post',
        ];

        DB::table('permissions')->insertOrIgnore(
            collect($permissions)->map(fn (string $name) => [
                'name' => $name,
                'guard_name' => $guardName,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );

        $permissionIds = DB::table('permissions')
            ->whereIn('name', $permissions)
            ->where('guard_name', $guardName)
            ->pluck('id');

        $roleIds = DB::table('roles')
            ->whereIn('name', ['super-admin', 'admin'])
            ->where('guard_name', $guardName)
            ->pluck('id');

        $pivotRows = [];
        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                $pivotRows[] = [
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ];
            }
        }

        DB::table('role_has_permissions')->insertOrIgnore($pivotRows);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissions = [
            'category-revenue-list',
            'category-revenue-create',
            'category-revenue-edit',
            'category-revenue-delete',
            'report-audit-revenue-detail',
            'revenue-detail-create',
            'revenue-detail-edit',
            'revenue-detail-delete',
            'revenue-detail-post',
        ];

        $permissionIds = DB::table('permissions')
            ->whereIn('name', $permissions)
            ->where('guard_name', 'web')
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
