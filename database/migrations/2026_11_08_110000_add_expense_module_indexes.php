<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes for the way the tables are read (company, shop, status, date, employee).
     *
     * @var array<string, array<string, array<int, string>>> table => index name => columns
     */
    private const INDEXES = [
        'expense_vouchers' => [
            'ev_vendor_status_idx' => ['expense_vendor_id', 'status', 'payment_type'],
            'ev_type_status_date_idx' => ['payment_type', 'status', 'voucher_date'],
        ],
        'expense_vendors' => ['ev_vendor_category_idx' => ['category', 'is_active']],
        'expense_vendor_payments' => [
            'evp_vendor_status_idx' => ['expense_vendor_id', 'status'],
            'evp_supplier_date_idx' => ['supplier_id', 'payment_date'],
            'evp_company_date_idx' => ['company_id', 'payment_date'],
            'evp_method_status_date_idx' => ['method', 'status', 'payment_date'],
        ],
        'employee_advances' => ['ea_employee_status_idx' => ['employee_id', 'status']],
        'payroll_runs' => ['pr_status_paid_idx' => ['status', 'paid_method', 'paid_on']],
    ];

    /**
     * Foreign key columns. MySQL indexes these by itself; PostgreSQL does not, so they are added there only.
     *
     * @var array<string, array<string, array<int, string>>>
     */
    private const POSTGRES_FOREIGN_KEY_INDEXES = [
        'expense_vouchers' => [
            'ev_category_idx' => ['expense_category_id'], 'ev_employee_idx' => ['employee_id'], 'ev_vehicle_idx' => ['vehicle_id'],
        ],
        'expense_vendor_payment_allocations' => ['evpa_payment_idx' => ['expense_vendor_payment_id'], 'evpa_voucher_idx' => ['expense_voucher_id']],
        'expense_voucher_attachments' => ['eva_voucher_idx' => ['expense_voucher_id']],
        'employee_advance_recoveries' => ['ear_advance_idx' => ['employee_advance_id'], 'ear_slip_idx' => ['payroll_slip_id']],
        'payroll_slips' => ['ps_employee_idx' => ['employee_id']],
        'payroll_slip_lines' => ['psl_slip_idx' => ['payroll_slip_id'], 'psl_advance_idx' => ['employee_advance_id']],
        'employee_salary_components' => ['esc_component_idx' => ['salary_component_id']],
    ];

    /**
     * Adds the indexes above, each only when missing, so running it on a database that already has some is safe.
     */
    public function up(): void
    {
        foreach ($this->wanted() as $table => $indexes) {
            foreach ($indexes as $name => $columns) {
                if (Schema::hasTable($table) && ! Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $name));
                }
            }
        }
    }

    /**
     * @return array<string, array<string, array<int, string>>>
     */
    private function wanted(): array
    {
        return Schema::getConnection()->getDriverName() === 'pgsql' ? array_merge_recursive(self::INDEXES, self::POSTGRES_FOREIGN_KEY_INDEXES) : self::INDEXES;
    }

    public function down(): void
    {
        foreach ($this->wanted() as $table => $indexes) {
            foreach (array_keys($indexes) as $name) {
                if (Schema::hasTable($table) && Schema::hasIndex($table, $name)) {
                    try {
                        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
                    } catch (Throwable) {
                        // An index a foreign key still relies on stays; it is harmless.
                    }
                }
            }
        }
    }
};
