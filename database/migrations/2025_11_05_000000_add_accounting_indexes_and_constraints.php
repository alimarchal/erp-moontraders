<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Adds missing indexes and NOT NULL constraints for better performance
     * and data integrity in the double-entry accounting system.
     */
    public function up(): void
    {
        // Add standalone index on journal_entries.status for better query performance
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->index('status');
        });

        // Add NOT NULL constraints to critical fields
        DB::statement("
            ALTER TABLE chart_of_accounts
            ALTER COLUMN account_name SET NOT NULL,
            ALTER COLUMN account_code SET NOT NULL,
            ALTER COLUMN normal_balance SET NOT NULL;
        ");

        DB::statement("
            ALTER TABLE cost_centers
            ALTER COLUMN name SET NOT NULL,
            ALTER COLUMN code SET NOT NULL;
        ");

        DB::statement("
            ALTER TABLE account_types
            ALTER COLUMN type_name SET NOT NULL,
            ALTER COLUMN report_group SET NOT NULL;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });

        // Note: Removing NOT NULL constraints would require data changes
        // If you need to roll back, you'll need to handle this manually
    }
};
