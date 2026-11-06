<?php

use App\Services\BankRegister\BankRegisterPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * The sixteen separate bank reports became two flexible ones (entries, summary) plus four fixed ones,
     * and the register ledger page was removed: add the new permissions, hand them to the roles that had
     * the old ones, and drop the retired permissions.
     */
    public function up(): void
    {
        $now = now();

        DB::table('permissions')->insertOrIgnore(array_map(fn (string $name) => [
            'name' => $name,
            'guard_name' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ], BankRegisterPermissions::REPORTS));

        $ids = DB::table('permissions')->where('guard_name', 'web')->whereIn('name', BankRegisterPermissions::REPORTS)->pluck('id', 'name');
        $roles = ['super-admin' => BankRegisterPermissions::REPORTS, 'admin' => BankRegisterPermissions::REPORTS] + BankRegisterPermissions::ROLE_REPORTS;

        foreach ($roles as $roleName => $names) {
            $roleId = DB::table('roles')->where('name', $roleName)->where('guard_name', 'web')->value('id');

            if ($roleId) {
                DB::table('role_has_permissions')->insertOrIgnore(
                    collect($names)->map(fn (string $name) => ['permission_id' => $ids[$name], 'role_id' => $roleId])->all()
                );
            }
        }

        $retired = DB::table('permissions')->where('guard_name', 'web')->whereIn('name', BankRegisterPermissions::RETIRED)->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $retired)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $retired)->delete();
        DB::table('permissions')->whereIn('id', $retired)->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Permissions only; the old report pages no longer exist, so there is nothing to restore.
    }
};
