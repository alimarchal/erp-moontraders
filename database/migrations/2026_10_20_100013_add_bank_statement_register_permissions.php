<?php

use App\Services\BankRegister\BankRegisterPermissions;
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

        DB::table('permissions')->insertOrIgnore(array_map(fn (string $name) => [
            'name' => $name,
            'guard_name' => $guardName,
            'created_at' => $now,
            'updated_at' => $now,
        ], BankRegisterPermissions::all()));

        $permissionIds = DB::table('permissions')
            ->whereIn('name', BankRegisterPermissions::all())
            ->where('guard_name', $guardName)
            ->pluck('id', 'name');

        // super-admin and admin receive everything when they exist; the bank roles receive their own slice.
        // Only the two roles this module introduces are created here; the others are given their grants
        // if they already exist (RoleAndPermissionSeeder creates them on a fresh install).
        $grants = [
            'super-admin' => BankRegisterPermissions::all(),
            'admin' => array_values(array_filter(
                BankRegisterPermissions::all(),
                fn (string $name) => ! str_contains($name, 'delete')
            )),
        ];

        foreach (array_keys(BankRegisterPermissions::ROLE_GRANTS) as $role) {
            $grants[$role] = BankRegisterPermissions::grantsFor($role);
        }

        foreach (['bank-checker', 'bank-senior-approver'] as $newRole) {
            DB::table('roles')->insertOrIgnore([
                'name' => $newRole,
                'guard_name' => $guardName,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($grants as $roleName => $permissionNames) {
            $roleId = DB::table('roles')->where('name', $roleName)->where('guard_name', $guardName)->value('id');

            if (! $roleId) {
                continue;
            }

            DB::table('role_has_permissions')->insertOrIgnore(
                collect($permissionNames)
                    ->map(fn (string $name) => ['permission_id' => $permissionIds[$name], 'role_id' => $roleId])
                    ->all()
            );
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', BankRegisterPermissions::all())
            ->where('guard_name', 'web')
            ->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
