<?php

use App\Support\MigrationHelpers;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = [
        'payroll-list', 'payroll-create', 'payroll-approve', 'payroll-pay', 'payroll-view-all', 'payroll-view-company',
        'payroll-salary-manage',
    ];

    /** @var array<int, array{string, string, string, string, float, string|null, int}> code, name, type, calculation, value, account code, sort */
    private const COMPONENTS = [
        ['house_rent', 'House Rent', 'earning', 'fixed', 0, null, 10],
        ['conveyance', 'Conveyance', 'earning', 'fixed', 0, null, 20],
        ['mobile', 'Mobile Allowance', 'earning', 'fixed', 0, null, 30],
        ['fuel', 'Fuel Allowance', 'earning', 'fixed', 0, null, 40],
        ['food', 'Food Allowance', 'earning', 'fixed', 0, null, 50],
        ['overtime', 'Overtime', 'earning', 'input', 0, null, 60],
        ['incentive', 'Incentive / Commission', 'earning', 'input', 0, null, 70],
        ['bonus', 'Bonus', 'earning', 'input', 0, null, 80],
        ['income_tax', 'Income Tax', 'deduction', 'input', 0, '2120', 110],
        ['eobi', 'EOBI', 'deduction', 'fixed', 0, '2120', 120],
        ['fine', 'Fine', 'deduction', 'input', 0, null, 130],
        ['loan', 'Loan Instalment', 'deduction', 'input', 0, '1132', 140],
    ];

    public function up(): void
    {
        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('type', 12);
            $table->string('calculation', 20)->default('fixed');
            $table->decimal('default_value', 15, 2)->default(0);
            $table->foreignId('account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained('employees')->cascadeOnDelete();
            $table->string('salary_type', 10)->default('basic');
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->string('pay_method', 10)->default('cash');
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('employee_salary_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('salary_component_id')->constrained('salary_components')->cascadeOnDelete();
            $table->decimal('value', 15, 2)->default(0);
            $table->timestamps();
            $table->unique(['employee_id', 'salary_component_id'], 'esc_unique');
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('run_number', 30)->unique();
            $table->date('period_month');
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('status', 20)->default('draft')->index();
            $table->decimal('total_earnings', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('total_net', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->string('paid_method', 10)->nullable();
            $table->foreignId('paid_bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->date('paid_on')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('payment_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['supplier_id', 'period_month']);
            $table->index(['company_id', 'period_month']);
        });

        Schema::create('payroll_slips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->string('salary_type', 10)->default('basic');
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->unsignedTinyInteger('days_in_month')->default(30);
            $table->decimal('absent_days', 5, 2)->default(0);
            $table->decimal('total_earnings', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('net_pay', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['payroll_run_id', 'employee_id']);
        });

        Schema::create('payroll_slip_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_slip_id')->constrained('payroll_slips')->cascadeOnDelete();
            $table->foreignId('salary_component_id')->nullable()->constrained('salary_components')->nullOnDelete();
            $table->foreignId('employee_advance_id')->nullable()->constrained('employee_advances')->nullOnDelete();
            $table->string('kind', 12);
            $table->string('source', 12);
            $table->string('label');
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });

        Schema::create('employee_advance_recoveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_advance_id')->constrained('employee_advances')->cascadeOnDelete();
            $table->foreignId('payroll_slip_id')->constrained('payroll_slips')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });

        $accounts = DB::table('chart_of_accounts')->whereIn('account_code', ['2120', '1132'])->pluck('id', 'account_code');
        $now = now();

        foreach (self::COMPONENTS as [$code, $name, $type, $calculation, $value, $accountCode, $sort]) {
            DB::table('salary_components')->insert([
                'code' => $code, 'name' => $name, 'type' => $type, 'calculation' => $calculation, 'default_value' => $value,
                'account_id' => $accountCode ? ($accounts[$accountCode] ?? null) : null, 'sort_order' => $sort,
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        MigrationHelpers::grantPermissions(self::PERMISSIONS);
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_advance_recoveries');
        Schema::dropIfExists('payroll_slip_lines');
        Schema::dropIfExists('payroll_slips');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('employee_salary_components');
        Schema::dropIfExists('employee_salaries');
        Schema::dropIfExists('salary_components');
        MigrationHelpers::dropPermissions(self::PERMISSIONS);
    }
};
