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
        Schema::create('product_price_change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();

            $table->enum('price_type', ['selling_price', 'expiry_price', 'cost_price']);

            $table->decimal('old_price', 15, 2)->nullable();
            $table->decimal('new_price', 15, 2)->nullable();

            // Batches impacted (selling_price changes only)
            $table->json('impacted_batch_ids')->nullable()->comment('stock_batch IDs whose selling_price was updated');
            $table->unsignedInteger('impacted_batch_count')->default(0);

            $table->timestamp('changed_at')->useCurrent();

            $table->index('product_id');
            $table->index('changed_by');
            $table->index('changed_at');
            $table->index('price_type');
        });

        // Create permission and assign to relevant roles
        $permission = DB::table('permissions')->insertOrIgnore([
            'name' => 'report-audit-product-price-change-log',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissionId = DB::table('permissions')
            ->where('name', 'report-audit-product-price-change-log')
            ->where('guard_name', 'web')
            ->value('id');

        $roleNames = ['super-admin', 'admin', 'inventory-manager'];
        $roleIds = DB::table('roles')
            ->whereIn('name', $roleNames)
            ->where('guard_name', 'web')
            ->pluck('id');

        $pivotRows = $roleIds->map(fn ($roleId) => [
            'permission_id' => $permissionId,
            'role_id' => $roleId,
        ])->all();

        DB::table('role_has_permissions')->insertOrIgnore($pivotRows);

        // Clear spatie permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_change_logs');

        $permissionId = DB::table('permissions')
            ->where('name', 'report-audit-product-price-change-log')
            ->where('guard_name', 'web')
            ->value('id');

        if ($permissionId) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
