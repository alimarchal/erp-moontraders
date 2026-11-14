<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The (grn_number, deleted_at) unique index does not stop two live GRNs sharing a number: deleted_at is NULL on
 * both, and NULLs never clash. Make the number unique among GRNs that are not deleted; a deleted GRN's number may
 * still be given out again, as the number generator does.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE goods_receipt_notes ADD COLUMN grn_number_live VARCHAR(50) AS (IF(deleted_at IS NULL, grn_number, NULL)) STORED INVISIBLE');
            DB::statement('CREATE UNIQUE INDEX goods_receipt_notes_grn_number_live_unique ON goods_receipt_notes (grn_number_live)');

            return;
        }

        DB::statement('CREATE UNIQUE INDEX goods_receipt_notes_grn_number_live_unique ON goods_receipt_notes (grn_number) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('DROP INDEX goods_receipt_notes_grn_number_live_unique ON goods_receipt_notes');
            DB::statement('ALTER TABLE goods_receipt_notes DROP COLUMN grn_number_live');

            return;
        }

        DB::statement('DROP INDEX goods_receipt_notes_grn_number_live_unique');
    }
};
