<?php

use App\Support\MigrationHelpers;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bonuses, commissions and other one-off pay for a chosen employee and month. They wait here and are pulled
     * into that month's payslip by themselves when payroll is built; approving the run marks them applied.
     */
    public function up(): void
    {
        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('salary_component_id')->constrained('salary_components')->restrictOnDelete();
            $table->date('period_month');
            $table->decimal('amount', 15, 2);
            $table->string('note', 300)->nullable();
            $table->string('status', 12)->default('pending');
            $table->foreignId('applied_payroll_run_id')->nullable()->constrained('payroll_runs')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'period_month', 'status'], 'pa_employee_month_status_idx');
            $table->index(['period_month', 'status'], 'pa_month_status_idx');
            $table->index('salary_component_id', 'pa_component_idx');
        });

        Schema::table('payroll_slip_lines', function (Blueprint $table) {
            $table->foreignId('payroll_adjustment_id')->nullable()->after('employee_advance_id')->constrained('payroll_adjustments')->nullOnDelete();
        });

        MigrationHelpers::grantPermissions(['payroll-adjustment-manage']);
    }

    public function down(): void
    {
        Schema::table('payroll_slip_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payroll_adjustment_id');
        });
        Schema::dropIfExists('payroll_adjustments');
        MigrationHelpers::dropPermissions(['payroll-adjustment-manage']);
    }
};
