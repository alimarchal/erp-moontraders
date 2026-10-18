<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Only super-admin and admin receive these here; other users get them through a role
     * on Settings → Roles. Created here so production only needs `php artisan migrate`.
     *
     * @var string[]
     */
    private array $permissions = [
        'company-target-list',
        'company-target-create',
        'company-target-edit',
        'company-target-delete',
        'report-audit-company-target-summary',
    ];

    public function up(): void
    {
        Schema::create('company_targets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('Public identifier used in URLs; the numeric id is never exposed');
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['supplier_id', 'period_start', 'period_end'], 'company_targets_supplier_period_unique');
            $table->index(['period_start', 'period_end']);
        });

        Schema::create('company_target_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_target_id')->constrained('company_targets')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('target_label')->default('Corporate')->comment('Free text shown in the Target column');
            // Signed on purpose: the form enforces >= 0 today, the column itself accepts negatives.
            $table->decimal('target_amount', 15, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['company_target_id', 'employee_id']);
        });

        $this->seedPermissions();
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', $this->permissions)
            ->where('guard_name', 'web')
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }

        Schema::dropIfExists('company_target_lines');
        Schema::dropIfExists('company_targets');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function seedPermissions(): void
    {
        $now = now();

        DB::table('permissions')->insertOrIgnore(
            collect($this->permissions)->map(fn (string $name) => [
                'name' => $name,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );

        $permissionIds = DB::table('permissions')
            ->whereIn('name', $this->permissions)
            ->where('guard_name', 'web')
            ->pluck('id');

        $roleIds = DB::table('roles')
            ->whereIn('name', ['super-admin', 'admin'])
            ->where('guard_name', 'web')
            ->pluck('id');

        $pivotRows = [];
        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                $pivotRows[] = ['permission_id' => $permissionId, 'role_id' => $roleId];
            }
        }

        if ($pivotRows !== []) {
            DB::table('role_has_permissions')->insertOrIgnore($pivotRows);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
