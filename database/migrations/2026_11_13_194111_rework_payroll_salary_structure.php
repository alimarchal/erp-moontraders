<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * New default categories. code, name, type, calculation, sort order.
     *
     * @var array<int, array{string, string, string, string, int}>
     */
    private const NEW_COMPONENTS = [
        ['utility', 'Utility Allowance', 'earning', 'fixed', 15],
        ['medical', 'Medical Allowance', 'earning', 'fixed', 25],
        ['death_insurance', 'Death Insurance', 'deduction', 'fixed', 115],
    ];

    /**
     * Payroll hub: an allowance can be a fixed amount, a percentage of basic or a percentage of another allowance, chosen per
     * employee; a payslip can waive the settlement shortage it would otherwise recover; EOBI is no longer used.
     */
    public function up(): void
    {
        Schema::table('employee_salary_components', function (Blueprint $table) {
            $table->string('calculation', 24)->nullable()->after('salary_component_id');
            $table->foreignId('base_component_id')->nullable()->after('calculation')->constrained('salary_components')->nullOnDelete();
        });

        Schema::table('payroll_slips', function (Blueprint $table) {
            $table->boolean('recover_settlement_short')->default(true)->after('absent_days');
        });

        $now = now();

        foreach (self::NEW_COMPONENTS as [$code, $name, $type, $calculation, $sort]) {
            DB::table('salary_components')->insertOrIgnore([
                'code' => $code, 'name' => $name, 'type' => $type, 'calculation' => $calculation, 'default_value' => 0,
                'account_id' => null, 'sort_order' => $sort, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // EOBI is switched off: payroll and the salary window skip inactive categories. Employees' rows and approved
        // payslips keep it, so rolling back restores everything as it was.
        DB::table('salary_components')->where('code', 'eobi')->update(['is_active' => false, 'updated_at' => $now]);
    }

    public function down(): void
    {
        DB::table('salary_components')->where('code', 'eobi')->update(['is_active' => true, 'updated_at' => now()]);

        $newIds = DB::table('salary_components')->whereIn('code', array_column(self::NEW_COMPONENTS, 0))->pluck('id');
        $used = DB::table('payroll_slip_lines')->whereIn('salary_component_id', $newIds)->exists()
            || DB::table('payroll_adjustments')->whereIn('salary_component_id', $newIds)->exists();

        if (! $used) {
            DB::table('employee_salary_components')->whereIn('salary_component_id', $newIds)->delete();
            DB::table('salary_components')->whereIn('id', $newIds)->delete();
        }

        Schema::table('payroll_slips', function (Blueprint $table) {
            $table->dropColumn('recover_settlement_short');
        });

        Schema::table('employee_salary_components', function (Blueprint $table) {
            $table->dropConstrainedForeignId('base_component_id');
            $table->dropColumn('calculation');
        });
    }
};
