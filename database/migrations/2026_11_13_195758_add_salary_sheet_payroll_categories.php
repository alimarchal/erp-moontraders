<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The columns of the distributors' monthly salary sheet. Fixed-part allowances are paid every month (set on the employee);
     * variable-part items depend on the month's achievement and are entered in the run's variable-pay grid.
     * code, name, type, calculation, sort order.
     *
     * @var array<int, array{string, string, string, string, int}>
     */
    private const CATEGORIES = [
        ['bike_maintenance', 'Bike Maintenance', 'earning', 'fixed', 32],
        ['bike_fuel', 'Bike Fuel', 'earning', 'fixed', 34],
        ['hht_regularity', 'HHT Regularity', 'earning', 'fixed', 36],
        ['old_employee_benefit', 'Old Employee Benefit', 'earning', 'fixed', 38],
        ['zero_return', 'Zero Return', 'earning', 'input', 61],
        ['mop_achievement', 'MOP Achievement', 'earning', 'input', 62],
        ['pos', 'POS', 'earning', 'input', 63],
        ['numerics', 'Numerics', 'earning', 'input', 64],
        ['punctuality', 'Punctuality', 'earning', 'input', 65],
        ['lppc', 'LPPC', 'earning', 'input', 66],
        ['distribution_incentive', 'Distribution Incentive', 'earning', 'input', 67],
        ['os_patrol', 'OS Patrol', 'earning', 'input', 68],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::CATEGORIES as [$code, $name, $type, $calculation, $sort]) {
            if (DB::table('salary_components')->where('code', $code)->orWhere('name', $name)->exists()) {
                continue;
            }

            DB::table('salary_components')->insert([
                'code' => $code, 'name' => $name, 'type' => $type, 'calculation' => $calculation, 'default_value' => 0,
                'account_id' => null, 'sort_order' => $sort, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        DB::table('salary_components')->where('code', 'death_insurance')->where('name', 'Death Insurance')->update(['name' => 'Death Fund', 'updated_at' => $now]);
    }

    public function down(): void
    {
        DB::table('salary_components')->where('code', 'death_insurance')->where('name', 'Death Fund')->update(['name' => 'Death Insurance', 'updated_at' => now()]);

        foreach (array_column(self::CATEGORIES, 0) as $code) {
            $id = DB::table('salary_components')->where('code', $code)->value('id');

            $used = $id && (DB::table('payroll_slip_lines')->where('salary_component_id', $id)->exists()
                || DB::table('payroll_adjustments')->where('salary_component_id', $id)->exists()
                || DB::table('employee_salary_components')->where('salary_component_id', $id)->exists());

            if ($id && ! $used) {
                DB::table('salary_components')->where('id', $id)->delete();
            }
        }
    }
};
