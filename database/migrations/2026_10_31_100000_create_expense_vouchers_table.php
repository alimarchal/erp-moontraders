<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = [
        'expense-voucher-list',
        'expense-voucher-create',
        'expense-voucher-edit',
        'expense-voucher-delete',
        'expense-voucher-approve',
        'expense-voucher-print',
    ];

    /**
     * Daily expense vouchers with a maker and a checker. Permissions are created here so production
     * only needs `php artisan migrate`; super-admin and admin receive them.
     */
    public function up(): void
    {
        Schema::create('expense_vouchers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('voucher_number', 30)->unique();
            $table->date('voucher_date')->index();
            // A voucher belongs to a distribution (supplier_id) or to Moon Traders itself (company_id, from the companies table).
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('party_name');
            $table->foreignId('expense_account_id')->constrained('chart_of_accounts');
            $table->decimal('amount', 15, 2);
            $table->text('narration')->nullable();
            $table->string('attachment')->nullable();
            $table->string('status', 20)->default('draft')->index();

            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamp('printed_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['supplier_id', 'voucher_date', 'status']);
            $table->index(['company_id', 'voucher_date', 'status']);
        });

        // One counter row per year, so voucher numbers (EV-2026-00001) never repeat, even with many users at once.
        Schema::create('expense_voucher_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
        });

        $now = now();

        DB::table('permissions')->insertOrIgnore(array_map(fn ($name) => [
            'name' => $name,
            'guard_name' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ], self::PERMISSIONS));

        $permissionIds = DB::table('permissions')->whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->pluck('id');
        $roleIds = DB::table('roles')->whereIn('name', ['super-admin', 'admin'])->where('guard_name', 'web')->pluck('id');

        $pivotRows = $roleIds->flatMap(fn ($roleId) => $permissionIds->map(fn ($permissionId) => [
            'permission_id' => $permissionId,
            'role_id' => $roleId,
        ]))->all();

        if ($pivotRows !== []) {
            DB::table('role_has_permissions')->insertOrIgnore($pivotRows);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_vouchers');
        Schema::dropIfExists('expense_voucher_sequences');

        $permissionIds = DB::table('permissions')->whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
