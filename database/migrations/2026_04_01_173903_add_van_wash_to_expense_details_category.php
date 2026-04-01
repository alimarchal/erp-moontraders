<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE expense_details DROP CONSTRAINT IF EXISTS expense_details_category_check');
            DB::statement("ALTER TABLE expense_details ADD CONSTRAINT expense_details_category_check CHECK (category IN ('stationary','tcs','tonner_it','salaries','fuel','van_work','van_wash'))");
        } else {
            DB::statement("ALTER TABLE expense_details MODIFY COLUMN category ENUM('stationary','tcs','tonner_it','salaries','fuel','van_work','van_wash') NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE expense_details DROP CONSTRAINT IF EXISTS expense_details_category_check');
            DB::statement("ALTER TABLE expense_details ADD CONSTRAINT expense_details_category_check CHECK (category IN ('stationary','tcs','tonner_it','salaries','fuel','van_work'))");
        } else {
            DB::statement("ALTER TABLE expense_details MODIFY COLUMN category ENUM('stationary','tcs','tonner_it','salaries','fuel','van_work') NOT NULL");
        }
    }
};
