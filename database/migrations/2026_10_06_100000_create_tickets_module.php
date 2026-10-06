<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Ready-made role for company (supplier) users: raise, edit and delete their own
     * tickets but never approve. Created here so production only needs `php artisan migrate`.
     */
    private const COMPANY_ROLE = 'company-user';

    /**
     * Ticket permissions. Only super-admin and admin receive them here;
     * company (supplier) users get them through a role on Settings → Roles.
     *
     * @var string[]
     */
    private array $permissions = [
        'ticket-list',
        'ticket-create',
        'ticket-edit',
        'ticket-delete',
        'ticket-approve',
    ];

    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('Public identifier used in URLs; the numeric id is never exposed');
            $table->string('ticket_number')->nullable()->unique();
            $table->string('title');
            $table->string('type', 30)->index()->comment('price_update | new_sku | reactivate_sku');
            $table->string('status', 20)->default('pending')->index()->comment('pending | approved | rejected');
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();

            // Selling price scope: every batch with stock, or only the listed stock_batches ids.
            $table->boolean('apply_to_all_batches')->default(true);
            $table->json('batch_ids')->nullable();

            $table->decimal('old_unit_sell_price', 15, 2)->nullable();
            $table->decimal('new_unit_sell_price', 15, 2)->nullable();
            $table->decimal('old_cost_price', 15, 2)->nullable();
            $table->decimal('new_cost_price', 15, 2)->nullable();
            $table->decimal('old_expiry_price', 15, 2)->nullable();
            $table->decimal('new_expiry_price', 15, 2)->nullable();
            $table->decimal('old_reorder_level', 10, 2)->nullable();
            $table->decimal('new_reorder_level', 10, 2)->nullable();

            $table->boolean('old_is_active')->nullable();
            $table->boolean('new_is_active')->nullable();

            $table->json('new_sku_data')->nullable()->comment('Full product payload for a New SKU ticket');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index('product_id');
        });

        Schema::create('ticket_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 30)->comment('created | updated | approved | rejected');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        $this->seedPermissions();
    }

    public function down(): void
    {
        $this->dropCompanyRole();

        $permissionIds = DB::table('permissions')
            ->whereIn('name', $this->permissions)
            ->where('guard_name', 'web')
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }

        Schema::dropIfExists('ticket_histories');
        Schema::dropIfExists('ticket_items');
        Schema::dropIfExists('tickets');

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

        $this->createCompanyRole($permissionIds->all());

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

    /**
     * @param  array<int, int>  $permissionIds
     */
    private function createCompanyRole(array $permissionIds): void
    {
        $now = now();

        DB::table('roles')->insertOrIgnore([
            'name' => self::COMPANY_ROLE,
            'guard_name' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $roleId = DB::table('roles')->where('name', self::COMPANY_ROLE)->where('guard_name', 'web')->value('id');

        $companyPermissionIds = DB::table('permissions')
            ->whereIn('id', $permissionIds)
            ->where('name', '!=', 'ticket-approve')
            ->pluck('id');

        DB::table('role_has_permissions')->insertOrIgnore(
            $companyPermissionIds->map(fn ($permissionId) => ['permission_id' => $permissionId, 'role_id' => $roleId])->all()
        );
    }

    /**
     * Remove the role only when nobody has been given it, so rolling back never strips users.
     */
    private function dropCompanyRole(): void
    {
        $roleId = DB::table('roles')->where('name', self::COMPANY_ROLE)->where('guard_name', 'web')->value('id');

        if ($roleId && ! DB::table('model_has_roles')->where('role_id', $roleId)->exists()) {
            DB::table('role_has_permissions')->where('role_id', $roleId)->delete();
            DB::table('roles')->where('id', $roleId)->delete();
        }
    }
};
