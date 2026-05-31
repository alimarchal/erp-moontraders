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
            'profit-category-list',
            'profit-category-create',
            'profit-category-edit',
            'profit-category-delete',
            'report-audit-profit-after-category',
            'profit-after-category-create',
            'profit-after-category-edit',
            'profit-after-category-delete',
            'profit-after-category-post',
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
            'profit-category-list',
            'profit-category-create',
            'profit-category-edit',
            'profit-category-delete',
            'report-audit-profit-after-category',
            'profit-after-category-create',
            'profit-after-category-edit',
            'profit-after-category-delete',
            'profit-after-category-post',
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
