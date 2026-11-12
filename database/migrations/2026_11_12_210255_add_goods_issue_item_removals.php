<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSION = 'goods-issue-remove-items';

    /**
     * A removal takes a line, or part of it, back from an issued goods issue before settlement.
     * This table is its audit row; the permission gates the action (super-admin and admin get it).
     */
    public function up(): void
    {
        Schema::create('goods_issue_item_removals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_issue_id')->constrained('goods_issues')->restrictOnDelete();
            $table->foreignId('goods_issue_item_id')->constrained('goods_issue_items')->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->decimal('cost_value', 15, 2);
            $table->decimal('selling_value', 15, 2);
            $table->text('reason');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->json('batches');
            $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('removed_at');
            $table->timestamps();

            $table->index(['goods_issue_id', 'removed_at']);
        });

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
        Schema::dropIfExists('goods_issue_item_removals');

        $permissionId = DB::table('permissions')->where('name', self::PERMISSION)->where('guard_name', 'web')->value('id');

        if ($permissionId) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('model_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
