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
        // This allows reusing GRN numbers after soft deletion
        DB::statement('CREATE UNIQUE INDEX goods_receipt_notes_grn_number_unique ON goods_receipt_notes (grn_number) WHERE deleted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the partial unique index
        DB::statement('DROP INDEX IF EXISTS goods_receipt_notes_grn_number_unique');

        // Restore the original simple unique constraint
        Schema::table('goods_receipt_notes', function (Blueprint $table) {
            $table->unique('grn_number');
        });
    }
};
