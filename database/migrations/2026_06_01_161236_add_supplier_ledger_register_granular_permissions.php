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
            'report-audit-ledger-register-create',
            'report-audit-ledger-register-edit',
            'report-audit-ledger-register-delete',
            'report-audit-ledger-register-set-opening-balance',
        ];

        DB::table('permissions')->insertOrIgnore(
            collect($permissions)->map(fn (string $name) => [
                'name' => $name,
                'guard_name' => $guardName,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );

        $newPermissionIds = DB::table('permissions')
            ->whereIn('name', $permissions)
            ->where('guard_name', $guardName)
            ->pluck('id');

        $managePermissionId = DB::table('permissions')
            ->where('name', 'report-audit-ledger-register-manage')
            ->where('guard_name', $guardName)
            ->value('id');

        if ($managePermissionId !== null) {
            $roleIds = DB::table('role_has_permissions')
                ->where('permission_id', $managePermissionId)
                ->pluck('role_id');

            $rolePivotRows = [];
            foreach ($roleIds as $roleId) {
                foreach ($newPermissionIds as $permissionId) {
                    $rolePivotRows[] = [
                        'permission_id' => $permissionId,
                        'role_id' => $roleId,
                    ];
                }
            }

            DB::table('role_has_permissions')->insertOrIgnore($rolePivotRows);

            $modelAssignments = DB::table('model_has_permissions')
                ->where('permission_id', $managePermissionId)
                ->get(['model_type', 'model_id']);

            $modelPivotRows = [];
            foreach ($modelAssignments as $assignment) {
                foreach ($newPermissionIds as $permissionId) {
                    $modelPivotRows[] = [
                        'permission_id' => $permissionId,
                        'model_type' => $assignment->model_type,
                        'model_id' => $assignment->model_id,
                    ];
                }
            }

            DB::table('model_has_permissions')->insertOrIgnore($modelPivotRows);
        }

        $adminRoleIds = DB::table('roles')
            ->whereIn('name', ['super-admin', 'admin'])
            ->where('guard_name', $guardName)
            ->pluck('id');

        $adminPivotRows = [];
        foreach ($adminRoleIds as $roleId) {
            foreach ($newPermissionIds as $permissionId) {
                $adminPivotRows[] = [
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ];
            }
        }

        DB::table('role_has_permissions')->insertOrIgnore($adminPivotRows);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissions = [
            'report-audit-ledger-register-create',
            'report-audit-ledger-register-edit',
            'report-audit-ledger-register-delete',
            'report-audit-ledger-register-set-opening-balance',
        ];

        $permissionIds = DB::table('permissions')
            ->whereIn('name', $permissions)
            ->where('guard_name', 'web')
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
