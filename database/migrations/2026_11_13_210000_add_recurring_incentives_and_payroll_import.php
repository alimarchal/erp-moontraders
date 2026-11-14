<?php

use App\Support\MigrationHelpers;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Incentives & adjustments can repeat every month (from period_month, until end_month or until stopped) instead of
     * applying to one month only. Salary set-up and incentives can be imported from Excel by holders of the new
     * payroll-import-excel permission (super-admin and admin).
     */
    public function up(): void
    {
        Schema::table('payroll_adjustments', function (Blueprint $table) {
            $table->boolean('is_recurring')->default(false)->after('note');
            $table->date('end_month')->nullable()->after('is_recurring');
            $table->index(['employee_id', 'is_recurring'], 'pa_employee_recurring_idx');
        });

        MigrationHelpers::grantPermissions(['payroll-import-excel']);
    }

    public function down(): void
    {
        Schema::table('payroll_adjustments', function (Blueprint $table) {
            $table->dropIndex('pa_employee_recurring_idx');
            $table->dropColumn(['is_recurring', 'end_month']);
        });

        MigrationHelpers::dropPermissions(['payroll-import-excel']);
    }
};
