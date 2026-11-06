<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Small helpers for data migrations, so production only needs `php artisan migrate`.
 */
class MigrationHelpers
{
    /**
     * Create the permissions and give them to the named roles.
     *
     * @param  array<int, string>  $permissions
     * @param  array<int, string>  $roles
     */
    public static function grantPermissions(array $permissions, array $roles = ['super-admin', 'admin']): void
    {
        $now = now();

        DB::table('permissions')->insertOrIgnore(array_map(fn ($name) => [
            'name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now,
        ], $permissions));

        $permissionIds = DB::table('permissions')->whereIn('name', $permissions)->where('guard_name', 'web')->pluck('id');
        $roleIds = DB::table('roles')->whereIn('name', $roles)->where('guard_name', 'web')->pluck('id');

        $rows = $roleIds->flatMap(fn ($roleId) => $permissionIds->map(fn ($permissionId) => ['permission_id' => $permissionId, 'role_id' => $roleId]))->all();

        if ($rows !== []) {
            DB::table('role_has_permissions')->insertOrIgnore($rows);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public static function dropPermissions(array $permissions): void
    {
        $ids = DB::table('permissions')->whereIn('name', $permissions)->where('guard_name', 'web')->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Add a leaf account under the account with $parentCode (a no-op when it already exists).
     */
    public static function addAccount(string $parentCode, string $code, string $name, string $normalBalance, string $description): void
    {
        $parent = DB::table('chart_of_accounts')->where('account_code', $parentCode)->first();

        if (! $parent || DB::table('chart_of_accounts')->where('account_code', $code)->exists()) {
            return;
        }

        DB::table('chart_of_accounts')->insert([
            'parent_id' => $parent->id,
            'account_type_id' => $parent->account_type_id,
            'currency_id' => $parent->currency_id,
            'account_code' => $code,
            'account_name' => $name,
            'normal_balance' => $normalBalance,
            'description' => $description,
            'is_group' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function dropAccount(string $code): void
    {
        $id = DB::table('chart_of_accounts')->where('account_code', $code)->value('id');

        if ($id && ! DB::table('journal_entry_details')->where('chart_of_account_id', $id)->exists()) {
            DB::table('chart_of_accounts')->where('id', $id)->delete();
        }
    }
}
