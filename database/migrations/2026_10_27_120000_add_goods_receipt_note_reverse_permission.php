<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSION = 'goods-receipt-note-reverse';

    /**
     * The Reverse GRN action is gated by this permission; super-admin and admin get it.
     */
    public function up(): void
    {
        DB::table('permissions')->insertOrIgnore([
            'name' => self::PERMISSION,
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissionId = DB::table('permissions')->where('name', self::PERMISSION)->where('guard_name', 'web')->value('id');

        DB::table('role_has_permissions')->insertOrIgnore(
            DB::table('roles')
                ->whereIn('name', ['super-admin', 'admin'])
                ->where('guard_name', 'web')
                ->pluck('id')
                ->map(fn ($roleId) => ['permission_id' => $permissionId, 'role_id' => $roleId])
                ->all()
        );

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', self::PERMISSION)->where('guard_name', 'web')->value('id');

        if ($permissionId) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('model_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
