<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_opening_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->date('date')->index();
            $table->string('description');
            $table->decimal('amount', 15, 2)->default(0);

            // System Fields
            $table->userTracking();
            $table->timestamps();

            $table->index(['supplier_id', 'date']);
        });

        // Nestle Pakistan seed values — others default to 0
        $nestleData = [
            'BANK_OPENING_AMOUNT'               => 0,
            'TOTAL_CASH_RECEIVED_CURRENT_MONTH' => 99209152.00,
            'TOTAL_ONLINE_AMOUNT_CURRENT_MONTH' => 99209152.00,
            'CLOSING_BALANCE_BEFORE_EXPENSES'   => 22209152.00,
            'TOTAL_EXPENSE_CURRENT_MONTH'       => 2259052.00,
            'CLOSING_BALANCE_AFTER_EXPENSE'     => 19950100.00,
            'LAST_MONTH_MAIN_INVESTMENT'        => 60393049.00,
            'CURRENT_MONTH_MAIN_INVESTMENT'     => 41636763.90,
            'NET_INVESTMENT'                    => 61586863.90,
            'INCREASE_INVESTMENT_CURRENT_MONTH' => 1193814.90,
        ];

        $descriptions = array_keys($nestleData);
        $nestleId = DB::table('suppliers')->where('supplier_name', 'like', 'Nestl%')->value('id');
        $suppliers = DB::table('suppliers')->pluck('id');
        $now = now();
        $rows = [];

        foreach ($suppliers as $supplierId) {
            foreach ($descriptions as $description) {
                $rows[] = [
                    'supplier_id' => $supplierId,
                    'date'        => '2026-03-31',
                    'description' => $description,
                    'amount'      => ($supplierId === $nestleId) ? $nestleData[$description] : 0,
                    'created_by'  => null,
                    'updated_by'  => null,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
            }
        }

        if (! empty($rows)) {
            DB::table('investment_opening_balances')->insert($rows);
        }

        // Permissions
        $permissions = [
            'investment-opening-balance-list',
            'investment-opening-balance-create',
            'investment-opening-balance-edit',
            'investment-opening-balance-delete',
        ];

        foreach ($permissions as $permName) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $permName,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $roleNames = ['super-admin', 'admin', 'inventory-manager'];
        $roleIds = DB::table('roles')
            ->whereIn('name', $roleNames)
            ->where('guard_name', 'web')
            ->pluck('id');

        $permissionIds = DB::table('permissions')
            ->whereIn('name', $permissions)
            ->where('guard_name', 'web')
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
        Schema::dropIfExists('investment_opening_balances');

        $permissions = [
            'investment-opening-balance-list',
            'investment-opening-balance-create',
            'investment-opening-balance-edit',
            'investment-opening-balance-delete',
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
