<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the existing unique constraint
        Schema::table('goods_receipt_notes', function (Blueprint $table) {
            $table->dropUnique(['grn_number']);
        });

        // Create a partial unique index that only applies to non-deleted records
        // PostgreSQL supports WHERE clause, MySQL/MariaDB doesn't
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // PostgreSQL: Use partial unique index
            DB::statement('CREATE UNIQUE INDEX goods_receipt_notes_grn_number_unique ON goods_receipt_notes (grn_number) WHERE deleted_at IS NULL');
        } else {
            // MySQL/MariaDB: Create unique index on (grn_number, deleted_at)
            // NULL values are not considered equal in MySQL unique indexes
            Schema::table('goods_receipt_notes', function (Blueprint $table) {
                $table->unique(['grn_number', 'deleted_at'], 'goods_receipt_notes_grn_number_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // Drop the partial unique index
            DB::statement('DROP INDEX IF EXISTS goods_receipt_notes_grn_number_unique');
        } else {
            // Drop the compound unique index
            Schema::table('goods_receipt_notes', function (Blueprint $table) {
                $table->dropUnique('goods_receipt_notes_grn_number_unique');
            });
        }

        // Restore the original simple unique constraint
        Schema::table('goods_receipt_notes', function (Blueprint $table) {
            $table->unique('grn_number');
        });
    }
};
