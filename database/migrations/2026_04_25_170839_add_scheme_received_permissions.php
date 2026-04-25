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

        $permissions = [
            'report-sales-scheme-received',
            'scheme-received-create',
            'scheme-received-edit',
            'scheme-received-delete',
        ];

        DB::table('permissions')->insertOrIgnore(
            collect($permissions)->map(fn ($name) => [
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
            ->whereIn('name', ['super-admin', 'admin', 'inventory-manager'])
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

    public function down(): void
    {
        $permissions = [
            'report-sales-scheme-received',
            'scheme-received-create',
            'scheme-received-edit',
            'scheme-received-delete',
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
