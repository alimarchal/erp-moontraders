<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSION = 'goods-issue-reverse';

    /**
     * A posted goods issue is never edited. When it was posted with a wrong salesman,
     * van or line, it is reversed (status `cancelled`, every stock, ledger and GL effect
     * undone by offsetting entries) and its lines are copied into a new draft that
     * `replaces_goods_issue_id` points back from.
     */
    public function up(): void
    {
        Schema::table('goods_issues', function (Blueprint $table) {
            $table->timestamp('reversed_at')->nullable()->after('posted_at');
            $table->foreignId('reversed_by')->nullable()->after('reversed_at')->constrained('users')->nullOnDelete();
            $table->text('reversal_reason')->nullable()->after('reversed_by');
            $table->foreignId('replaces_goods_issue_id')->nullable()->after('reversal_reason')->constrained('goods_issues')->nullOnDelete();
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
        $permissionId = DB::table('permissions')->where('name', self::PERMISSION)->where('guard_name', 'web')->value('id');

        if ($permissionId) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('model_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Schema::table('goods_issues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('replaces_goods_issue_id');
            $table->dropColumn('reversal_reason');
            $table->dropConstrainedForeignId('reversed_by');
            $table->dropColumn('reversed_at');
        });
    }
};
