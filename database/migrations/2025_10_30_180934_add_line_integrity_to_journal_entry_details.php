<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('journal_entry_details', function (Blueprint $table) {
            // Add line number for ordering - NO DEFAULT to avoid unique constraint collisions
            // Application MUST set line_no explicitly (1, 2, 3, ...)
            $table->unsignedInteger('line_no')->after('journal_entry_id')
                ->comment('Line number within the journal entry. Must be set explicitly by application.');

            // Add unique constraint for journal_entry_id + line_no
            $table->unique(['journal_entry_id', 'line_no'], 'ux_journal_line');

            // Add indexes for reporting
            $table->index('chart_of_account_id');
            $table->index('cost_center_id');
        });

        // Add check constraint for debit XOR credit (PostgreSQL/MySQL 8+ only, not SQLite)
        // Either debit > 0 and credit = 0, OR credit > 0 and debit = 0
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'sqlite') {
            DB::statement('ALTER TABLE journal_entry_details ADD CONSTRAINT chk_debit_xor_credit 
                CHECK (
                    (debit > 0 AND credit = 0) OR 
                    (credit > 0 AND debit = 0) OR
                    (debit = 0 AND credit = 0)
                )');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        // Drop constraint only if not SQLite
        if ($driver !== 'sqlite') {
            DB::statement('ALTER TABLE journal_entry_details DROP CONSTRAINT IF EXISTS chk_debit_xor_credit');
        }

        Schema::table('journal_entry_details', function (Blueprint $table) {
            $table->dropUnique('ux_journal_line');
            $table->dropIndex(['chart_of_account_id']);
            $table->dropIndex(['cost_center_id']);
            $table->dropColumn('line_no');
        });
    }
};
