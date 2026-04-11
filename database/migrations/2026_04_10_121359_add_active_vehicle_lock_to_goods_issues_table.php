<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds an `active_vehicle_lock` column to enforce, at the database level,
     * that a vehicle can only have ONE active Goods Issue at a time.
     *
     * "Active" means the GI workflow is incomplete — the GI is `draft` (not
     * yet posted) OR `issued` with a settlement that is not yet `posted`.
     * Once the linked settlement is posted (or the GI is soft-deleted), the
     * lock is released so the vehicle is free for the next issue.
     *
     * Implementation: when active, the column holds the `vehicle_id`; when
     * not active, it holds NULL. A unique index then guarantees at most one
     * non-NULL row per vehicle (NULLs do not conflict in MySQL/MariaDB unique
     * indexes). Application code in `GoodsIssue::booted()`, `DistributionService`,
     * and `SalesSettlementRevertService` keeps the column in sync with the
     * workflow state.
     */
    public function up(): void
    {
        Schema::table('goods_issues', function (Blueprint $table) {
            $table->unsignedBigInteger('active_vehicle_lock')->nullable()->after('vehicle_id');
        });

        // Backfill: set the column for every GI whose workflow is currently
        // incomplete. Mirrors the validation rule in StoreGoodsIssueRequest so
        // existing data agrees with the new application logic. Written in
        // portable SQL (subqueries instead of UPDATE...JOIN) so it runs on
        // both MariaDB (production) and PostgreSQL (test).
        DB::statement("
            UPDATE goods_issues
            SET active_vehicle_lock = vehicle_id
            WHERE deleted_at IS NULL
              AND status IN ('draft', 'issued')
              AND (
                  status = 'draft'
                  OR id NOT IN (
                      SELECT goods_issue_id FROM sales_settlements
                      WHERE deleted_at IS NULL AND goods_issue_id IS NOT NULL
                  )
                  OR id IN (
                      SELECT goods_issue_id FROM sales_settlements
                      WHERE deleted_at IS NULL
                        AND goods_issue_id IS NOT NULL
                        AND status IN ('draft', 'verified')
                  )
              )
        ");

        // Sanity check: if backfill produced any duplicates the unique index
        // would fail to add. Surface a clear error so the operator can clean
        // up the offending rows before re-running the migration.
        $duplicates = DB::table('goods_issues')
            ->select('active_vehicle_lock', DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('active_vehicle_lock')
            ->groupBy('active_vehicle_lock')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            $vehicles = $duplicates->pluck('active_vehicle_lock')->implode(', ');
            throw new RuntimeException(
                "Cannot add unique active_vehicle_lock index: vehicles [{$vehicles}] already have multiple active Goods Issues. Resolve them before re-running this migration."
            );
        }

        Schema::table('goods_issues', function (Blueprint $table) {
            $table->unique('active_vehicle_lock', 'goods_issues_active_vehicle_lock_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('goods_issues', function (Blueprint $table) {
            $table->dropUnique('goods_issues_active_vehicle_lock_unique');
            $table->dropColumn('active_vehicle_lock');
        });
    }
};
