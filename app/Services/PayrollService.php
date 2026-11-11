<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\EmployeeSalary;
use App\Models\PayrollAdjustment;
use App\Models\PayrollRun;
use App\Models\PayrollSlip;
use App\Models\PayrollSlipLine;
use App\Models\SalaryComponent;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Monthly salary of one company. One button builds a payslip for every employee with a salary: basic pay,
 * allowances, the absent-day deduction and the advance instalment all come out of the set-up, so nobody types
 * them in. The maker only adds what is special this month (overtime, a fine, tax). Approval posts the ledger
 * entry and records each advance recovery; paying clears the salary payable.
 */
class PayrollService
{
    public const SALARY_ACCOUNT = '5281';

    public const PAYABLE_ACCOUNT = '2112';

    public const ADVANCES_ACCOUNT = '1131';

    /** Settlement Excess Income: the settlement books a cash excess here until payroll hands it to the salesman. */
    public const EXCESS_ACCOUNT = '4250';

    public function __construct(private AccountingService $accounting, private DocumentNumberService $numbers) {}

    /**
     * Employees who are active and have a salary set. All of them work for Moon Traders: with no supplier given, every one
     * of them is included (the whole payroll of Moon Traders in one run); with a supplier, only that supplier's staff.
     *
     * @return Collection<int, Employee>
     */
    public function payableEmployees(?int $supplierId, ?int $companyId): Collection
    {
        return Employee::query()
            ->where('is_active', true)
            ->whereHas('salary', fn ($query) => $query->where('basic_salary', '>', 0))
            ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
            ->with(['salary', 'salaryComponents.component', 'supplier:id,supplier_name,short_name'])
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Employee> active employees without a salary yet (of one supplier, or everyone when none is given)
     */
    public function employeesWithoutSalary(?int $supplierId): Collection
    {
        return Employee::query()
            ->where('is_active', true)
            ->doesntHave('salary')
            ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
            ->orderBy('name')
            ->get(['id', 'name', 'employee_code', 'designation']);
    }

    public function createRun(?int $supplierId, ?int $companyId, CarbonInterface $month, User $maker): PayrollRun
    {
        $month = $month->copy()->startOfMonth();

        // One employee must never be paid twice for a month: a run for all employees (Moon Traders) excludes every other run
        // of that month, and a run for one supplier's staff excludes a run for all employees and a second run of the same supplier.
        $sameMonth = PayrollRun::query()->whereDate('period_month', $month->toDateString());

        if ($supplierId === null && (clone $sameMonth)->exists()) {
            throw new RuntimeException('Salary for '.$month->format('F Y').' has already been started, so it cannot be started again for all employees.');
        }

        if ($supplierId !== null && (clone $sameMonth)->where(fn ($query) => $query->where('supplier_id', $supplierId)->orWhereNull('supplier_id'))->exists()) {
            throw new RuntimeException('Salary for '.$month->format('F Y').' has already been started for this supplier or for all employees.');
        }

        $employees = $this->payableEmployees($supplierId, $companyId);

        if ($employees->isEmpty()) {
            throw new RuntimeException('No active employee has a salary set up yet.');
        }

        return DB::transaction(function () use ($supplierId, $companyId, $month, $employees) {
            $run = PayrollRun::create([
                'run_number' => $this->numbers->next('payroll', 'PR'),
                'period_month' => $month,
                'supplier_id' => $supplierId,
                'company_id' => $supplierId ? null : $companyId,
                'status' => PayrollRun::STATUS_DRAFT,
            ]);

            foreach ($employees as $employee) {
                $slip = $run->slips()->create([
                    'employee_id' => $employee->id,
                    'salary_type' => $employee->salary->salary_type,
                    'basic_salary' => $employee->salary->basic_salary,
                    'days_in_month' => $month->daysInMonth,
                ]);
                $this->rebuild($slip->setRelation('run', $run), $employee);
            }

            $this->refreshRunTotals($run);

            return $run->fresh();
        });
    }

    /**
     * Rebuild a slip's automatic lines (basic, allowances, absent days, advance instalments) from the salary
     * set-up, keeping the lines the maker added by hand.
     */
    public function rebuild(PayrollSlip $slip, ?Employee $employee = null): PayrollSlip
    {
        $this->ensureEditable($slip->run);

        return DB::transaction(fn () => $this->doRebuild($slip, $employee));
    }

    private function doRebuild(PayrollSlip $slip, ?Employee $employee): PayrollSlip
    {
        $employee ??= $slip->employee()->with(['salary', 'salaryComponents.component'])->first();
        $salary = $employee->salary ?? new EmployeeSalary(['salary_type' => 'basic', 'basic_salary' => 0]);

        $slip->lines()->whereIn('source', [PayrollSlipLine::SOURCE_BASIC, PayrollSlipLine::SOURCE_ALLOWANCE, PayrollSlipLine::SOURCE_ABSENT, PayrollSlipLine::SOURCE_ADVANCE, PayrollSlipLine::SOURCE_ADJUSTMENT, PayrollSlipLine::SOURCE_EXCESS])->delete();

        $basic = (float) $salary->basic_salary;
        $slip->update(['salary_type' => $salary->salary_type, 'basic_salary' => $basic]);

        if ($basic > 0) {
            $this->addLine($slip, 'earning', PayrollSlipLine::SOURCE_BASIC, 'Basic salary', $basic);
        }

        foreach ($employee->salaryComponents as $assigned) {
            $component = $assigned->component;

            if (! $component || ! $component->is_active || $component->calculation === SalaryComponent::CALC_INPUT) {
                continue;
            }

            $amount = $component->calculation === SalaryComponent::CALC_PERCENT ? round($basic * (float) $assigned->value / 100, 2) : (float) $assigned->value;

            if ($amount > 0) {
                $this->addLine($slip, $component->type, PayrollSlipLine::SOURCE_ALLOWANCE, $component->name, $amount, $component->id);
            }
        }

        if ($salary->salary_type === EmployeeSalary::TYPE_BASIC && (float) $slip->absent_days > 0 && $basic > 0) {
            $perDay = $basic / max(1, (int) $slip->days_in_month);
            $this->addLine($slip, 'deduction', PayrollSlipLine::SOURCE_ABSENT, 'Absent '.rtrim(rtrim(number_format((float) $slip->absent_days, 2), '0'), '.').' day(s)', round($perDay * (float) $slip->absent_days, 2));
        }

        $this->addAdjustmentLines($slip, $employee);
        $this->addExcessLine($slip, $employee);
        $this->addAdvanceLines($slip, $employee);

        return $this->refreshSlipTotals($slip);
    }

    /**
     * Bonuses, commissions and other one-off pay decided for this employee and month.
     */
    private function addAdjustmentLines(PayrollSlip $slip, Employee $employee): void
    {
        $month = $slip->run->period_month->copy()->startOfMonth()->toDateString();

        $adjustments = PayrollAdjustment::query()
            ->with('component')
            ->where('employee_id', $employee->id)
            ->whereDate('period_month', $month)
            ->where('status', PayrollAdjustment::STATUS_PENDING)
            ->orderBy('id')
            ->get();

        foreach ($adjustments as $adjustment) {
            $slip->lines()->create([
                'salary_component_id' => $adjustment->salary_component_id,
                'payroll_adjustment_id' => $adjustment->id,
                'kind' => $adjustment->component->type,
                'source' => PayrollSlipLine::SOURCE_ADJUSTMENT,
                'label' => $adjustment->component->name.($adjustment->note ? ' - '.$adjustment->note : ''),
                'amount' => $adjustment->amount,
            ]);
        }
    }

    /**
     * The cash excess of the employee's posted settlements in the month is his earning. The settlement booked it to
     * Settlement Excess Income (4250); approving the payroll moves it from there to the salary payable.
     */
    private function addExcessLine(PayrollSlip $slip, Employee $employee): void
    {
        $month = $slip->run->period_month;

        $excess = DB::table('sales_settlement_excess_amounts as excess')
            ->join('sales_settlements as settlements', 'settlements.id', '=', 'excess.sales_settlement_id')
            ->where('settlements.employee_id', $employee->id)
            ->where('settlements.status', 'posted')
            ->whereNull('settlements.deleted_at')
            ->whereBetween('settlements.settlement_date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->selectRaw('COALESCE(SUM(excess.amount), 0) as total, COUNT(DISTINCT settlements.id) as settlements')
            ->first();

        $total = round((float) $excess->total, 2);

        if ($total > 0) {
            $this->addLine($slip, 'earning', PayrollSlipLine::SOURCE_EXCESS, 'Settlement excess ('.$excess->settlements.' '.str('settlement')->plural((int) $excess->settlements).')', $total);
        }
    }

    /**
     * Employees who became payable after this run was built (a new joiner, a salary set later) get a slip too.
     *
     * @return Collection<int, Employee>
     */
    public function missingEmployees(PayrollRun $run): Collection
    {
        $have = $run->slips()->pluck('employee_id');

        return $this->payableEmployees($run->supplier_id, $run->company_id)->reject(fn (Employee $employee) => $have->contains($employee->id))->values();
    }

    public function addMissingEmployees(PayrollRun $run): int
    {
        $this->ensureEditable($run);

        return DB::transaction(function () use ($run) {
            $added = $this->missingEmployees($run);

            foreach ($added as $employee) {
                $slip = $run->slips()->create([
                    'employee_id' => $employee->id,
                    'salary_type' => $employee->salary->salary_type,
                    'basic_salary' => $employee->salary->basic_salary,
                    'days_in_month' => $run->period_month->daysInMonth,
                ]);
                $this->rebuild($slip->setRelation('run', $run), $employee);
            }

            $this->refreshRunTotals($run);

            return $added->count();
        });
    }

    /**
     * Put a newly saved adjustment on the slip right away when that month's run is still open to changes.
     */
    public function refreshAdjustment(PayrollAdjustment $adjustment): void
    {
        $employee = Employee::find($adjustment->employee_id);

        $slip = PayrollSlip::query()
            ->where('employee_id', $adjustment->employee_id)
            ->whereHas('run', fn ($query) => $query->whereDate('period_month', $adjustment->period_month->toDateString())->whereIn('status', [PayrollRun::STATUS_DRAFT, PayrollRun::STATUS_REJECTED]))
            ->with('run')
            ->first();

        if ($slip && $employee) {
            $this->rebuild($slip, $employee);
        }
    }

    /**
     * An adjustment can be added or removed while no approved run exists for that employee's month.
     */
    public function monthIsLocked(Employee $employee, CarbonInterface $month): bool
    {
        return PayrollSlip::query()
            ->where('employee_id', $employee->id)
            ->whereHas('run', fn ($query) => $query->whereDate('period_month', $month->copy()->startOfMonth()->toDateString())->whereIn('status', [PayrollRun::STATUS_PENDING, PayrollRun::STATUS_APPROVED, PayrollRun::STATUS_PAID]))
            ->exists();
    }

    /**
     * Take what is due from each open advance, never more than the employee has left after the other lines.
     */
    private function addAdvanceLines(PayrollSlip $slip, Employee $employee): void
    {
        $slip->load('lines');
        $available = (float) $slip->lines->where('kind', 'earning')->sum('amount') - (float) $slip->lines->where('kind', 'deduction')->sum('amount');

        $advances = EmployeeAdvance::query()
            ->where('employee_id', $employee->id)
            ->where('status', EmployeeAdvance::STATUS_OPEN)
            ->orderBy('advance_date')->orderBy('id')
            ->get();

        foreach ($advances as $advance) {
            $due = min($advance->dueThisMonth(), max(0, round($available, 2)));

            if ($due <= 0) {
                continue;
            }

            $this->addLine($slip, 'deduction', PayrollSlipLine::SOURCE_ADVANCE, 'Advance of '.$advance->advance_date->format('d M Y'), $due, null, $advance->id);
            $available -= $due;
        }
    }

    /**
     * An extra earning or deduction for this month only: overtime, incentive, bonus, fine, tax...
     */
    public function addManualLine(PayrollSlip $slip, SalaryComponent $component, float $amount, ?string $label = null): PayrollSlip
    {
        $this->ensureEditable($slip->run);

        if ($amount <= 0) {
            throw new RuntimeException('The amount must be more than zero.');
        }

        return DB::transaction(function () use ($slip, $component, $amount, $label) {
            $this->addLine($slip, $component->type, PayrollSlipLine::SOURCE_MANUAL, $label ?: $component->name, $amount, $component->id);

            return $this->refreshSlipTotals($slip);
        });
    }

    public function removeLine(PayrollSlipLine $line): PayrollSlip
    {
        $slip = $line->slip->load('run');
        $this->ensureEditable($slip->run);

        if ($line->source !== PayrollSlipLine::SOURCE_MANUAL) {
            throw new RuntimeException('Only a line you added yourself can be removed. Change the salary set-up and recalculate instead.');
        }

        return DB::transaction(function () use ($line, $slip) {
            $line->delete();

            return $this->refreshSlipTotals($slip);
        });
    }

    public function setAbsentDays(PayrollSlip $slip, float $days): PayrollSlip
    {
        $this->ensureEditable($slip->run);

        if ($days < 0 || $days > $slip->days_in_month) {
            throw new RuntimeException('Absent days must be between 0 and '.$slip->days_in_month.'.');
        }

        return DB::transaction(function () use ($slip, $days) {
            $slip->update(['absent_days' => $days]);

            return $this->rebuild($slip);
        });
    }

    public function recalculateRun(PayrollRun $run): PayrollRun
    {
        $this->ensureEditable($run);

        DB::transaction(function () use ($run) {
            foreach ($run->slips()->with('employee.salary', 'employee.salaryComponents.component')->get() as $slip) {
                $this->rebuild($slip->setRelation('run', $run), $slip->employee);
            }
            $this->refreshRunTotals($run);
        });

        return $run->fresh();
    }

    public function submit(PayrollRun $run, User $maker): PayrollRun
    {
        $this->ensureEditable($run);

        $negative = $run->slips()->with('employee:id,name')->where('net_pay', '<', 0)->first();
        if ($negative) {
            throw new RuntimeException($negative->employee->name.' has a negative net pay. Reduce a deduction before sending the run for approval.');
        }

        if (! $run->slips()->exists()) {
            throw new RuntimeException('This run has no payslips.');
        }

        // Check the run and move it on under one lock so two people cannot change it at the same time.
        $run->update([
            'status' => PayrollRun::STATUS_PENDING, 'submitted_by' => $maker->id, 'submitted_at' => now(),
            'checked_by' => null, 'checked_at' => null, 'rejection_reason' => null,
        ]);

        return $run->fresh();
    }

    public function reject(PayrollRun $run, User $checker, string $reason): PayrollRun
    {
        $this->ensurePendingFor($run, $checker);

        $run->update(['status' => PayrollRun::STATUS_REJECTED, 'checked_by' => $checker->id, 'checked_at' => now(), 'rejection_reason' => $reason]);

        return $run->fresh();
    }

    /**
     * Posts Dr salary / allowance accounts, Cr deductions (advances, tax, fines) and Cr Payroll Payable (net),
     * and records the advance instalments so payroll never takes the same money twice.
     */
    public function approve(PayrollRun $run, User $checker): PayrollRun
    {
        $this->ensurePendingFor($run, $checker);

        return DB::transaction(function () use ($run, $checker) {
            $run->load('slips.lines.component');
            $this->checkAdvances($run);

            $salaryId = $this->accountId(self::SALARY_ACCOUNT, 'Salary (5281)');
            $payableId = $this->accountId(self::PAYABLE_ACCOUNT, 'Payroll Payable (2112)');
            $advancesId = $this->accountId(self::ADVANCES_ACCOUNT, 'Employee Advances (1131)');

            $excessId = $run->slips->flatMap->lines->contains('source', PayrollSlipLine::SOURCE_EXCESS)
                ? $this->accountId(self::EXCESS_ACCOUNT, 'Settlement Excess Income (4250)')
                : null;

            $debits = [];
            $credits = [];

            foreach ($run->slips->flatMap->lines as $line) {
                $account = match ($line->source) {
                    PayrollSlipLine::SOURCE_ADVANCE => $advancesId,
                    PayrollSlipLine::SOURCE_EXCESS => $excessId,
                    default => $line->component?->account_id ?: $salaryId,
                };

                if ($line->kind === 'earning') {
                    $debits[$account] = ($debits[$account] ?? 0) + (float) $line->amount;
                } else {
                    $credits[$account] = ($credits[$account] ?? 0) + (float) $line->amount;
                }
            }

            $description = 'Salary '.$run->period_month->format('F Y').' - '.$run->ownerLabel().' ('.$run->run_number.')';
            $lines = [];
            foreach ($debits as $account => $amount) {
                $lines[] = ['account_id' => $account, 'debit' => round($amount, 2), 'credit' => 0, 'description' => $description, 'cost_center_id' => 1];
            }
            foreach ($credits as $account => $amount) {
                $lines[] = ['account_id' => $account, 'debit' => 0, 'credit' => round($amount, 2), 'description' => $description, 'cost_center_id' => 1];
            }
            $lines[] = ['account_id' => $payableId, 'debit' => 0, 'credit' => round((float) $run->slips->sum('net_pay'), 2), 'description' => $description, 'cost_center_id' => 1];

            $result = $this->accounting->createJournalEntry([
                'entry_date' => $run->period_month->copy()->endOfMonth()->toDateString(),
                'reference' => $run->run_number,
                'description' => $description,
                'reference_type' => PayrollRun::class,
                'reference_id' => $run->id,
                'lines' => $lines,
                'auto_post' => true,
            ]);

            if (! $result['success']) {
                throw new RuntimeException('Posting failed: '.$result['message']);
            }

            foreach ($run->slips as $slip) {
                foreach ($slip->lines->where('source', PayrollSlipLine::SOURCE_ADVANCE) as $line) {
                    $this->recordRecovery($slip, $line);
                }
            }

            $adjustmentIds = $run->slips->flatMap->lines->where('source', PayrollSlipLine::SOURCE_ADJUSTMENT)->pluck('payroll_adjustment_id')->filter()->all();
            PayrollAdjustment::query()->whereIn('id', $adjustmentIds)->update(['status' => PayrollAdjustment::STATUS_APPLIED, 'applied_payroll_run_id' => $run->id]);

            $run->update([
                'status' => PayrollRun::STATUS_APPROVED, 'checked_by' => $checker->id, 'checked_at' => now(),
                'journal_entry_id' => $result['data']->id, 'rejection_reason' => null,
            ]);

            return $run->fresh();
        });
    }

    /**
     * Pay the salaries: Dr Payroll Payable / Cr Cash or the bank account. A cash payment lands in that day's
     * cash collection report as money paid out.
     */
    public function pay(PayrollRun $run, User $payer, string $method, ?int $bankAccountId, string $paidOn): PayrollRun
    {
        if ($run->status !== PayrollRun::STATUS_APPROVED) {
            throw new RuntimeException('Only an approved run can be paid.');
        }

        return DB::transaction(function () use ($run, $payer, $method, $bankAccountId, $paidOn) {
            $bank = $method === 'bank' ? BankAccount::find($bankAccountId) : null;

            if ($method === 'bank' && ! $bank?->chart_of_account_id) {
                throw new RuntimeException('Choose a bank account that is linked to a ledger account.');
            }

            $from = $bank ? (int) $bank->chart_of_account_id : $this->accountId('1121', 'Cash (1121)');
            $description = 'Salary paid '.$run->period_month->format('F Y').' - '.$run->ownerLabel().' ('.$run->run_number.')';
            $amount = (float) $run->total_net;

            $result = $this->accounting->createJournalEntry([
                'entry_date' => $paidOn,
                'reference' => $run->run_number.'-PAY',
                'description' => $description,
                'reference_type' => PayrollRun::class,
                'reference_id' => $run->id,
                'lines' => [
                    ['account_id' => $this->accountId(self::PAYABLE_ACCOUNT, 'Payroll Payable (2112)'), 'debit' => $amount, 'credit' => 0, 'description' => $description, 'cost_center_id' => 1],
                    ['account_id' => $from, 'debit' => 0, 'credit' => $amount, 'description' => $description, 'cost_center_id' => 1],
                ],
                'auto_post' => true,
            ]);

            if (! $result['success']) {
                throw new RuntimeException('Posting failed: '.$result['message']);
            }

            $run->update([
                'status' => PayrollRun::STATUS_PAID, 'paid_method' => $method, 'paid_bank_account_id' => $bank?->id,
                'paid_on' => $paidOn, 'paid_by' => $payer->id, 'payment_journal_entry_id' => $result['data']->id,
            ]);

            return $run->fresh();
        });
    }

    public function delete(PayrollRun $run): void
    {
        $this->ensureEditable($run);

        $run->delete();
    }

    private function recordRecovery(PayrollSlip $slip, PayrollSlipLine $line): void
    {
        $advance = EmployeeAdvance::query()->lockForUpdate()->findOrFail($line->employee_advance_id);
        $recovered = round((float) $advance->recovered_amount + (float) $line->amount, 2);

        $advance->recoveries()->create(['payroll_slip_id' => $slip->id, 'amount' => $line->amount]);
        $advance->update([
            'recovered_amount' => $recovered,
            'status' => $recovered + 0.001 >= (float) $advance->amount ? EmployeeAdvance::STATUS_RECOVERED : EmployeeAdvance::STATUS_OPEN,
        ]);
    }

    private function checkAdvances(PayrollRun $run): void
    {
        $perAdvance = $run->slips->flatMap->lines->where('source', PayrollSlipLine::SOURCE_ADVANCE)->groupBy('employee_advance_id');

        foreach ($perAdvance as $advanceId => $lines) {
            $advance = EmployeeAdvance::query()->lockForUpdate()->find($advanceId);

            if (! $advance || (float) $lines->sum('amount') > $advance->balance() + 0.001) {
                throw new RuntimeException('An advance has changed since this run was made (it may already be recovered). Recalculate the run and send it again.');
            }
        }
    }

    private function addLine(PayrollSlip $slip, string $kind, string $source, string $label, float $amount, ?int $componentId = null, ?int $advanceId = null): void
    {
        $slip->lines()->create([
            'salary_component_id' => $componentId, 'employee_advance_id' => $advanceId,
            'kind' => $kind, 'source' => $source, 'label' => $label, 'amount' => round($amount, 2),
        ]);
    }

    private function refreshSlipTotals(PayrollSlip $slip): PayrollSlip
    {
        $lines = $slip->lines()->get();
        $earnings = round((float) $lines->where('kind', 'earning')->sum('amount'), 2);
        $deductions = round((float) $lines->where('kind', 'deduction')->sum('amount'), 2);

        $slip->update(['total_earnings' => $earnings, 'total_deductions' => $deductions, 'net_pay' => round($earnings - $deductions, 2)]);
        $this->refreshRunTotals($slip->run);

        return $slip->fresh();
    }

    private function refreshRunTotals(PayrollRun $run): void
    {
        $totals = $run->slips()->selectRaw('COALESCE(SUM(total_earnings),0) e, COALESCE(SUM(total_deductions),0) d, COALESCE(SUM(net_pay),0) n')->first();

        $run->update(['total_earnings' => $totals->e, 'total_deductions' => $totals->d, 'total_net' => $totals->n]);
    }

    private function ensureEditable(PayrollRun $run): void
    {
        if (! $run->isEditable()) {
            throw new RuntimeException('A run that has been sent to the checker can no longer be changed.');
        }
    }

    private function ensurePendingFor(PayrollRun $run, User $checker): void
    {
        if ($run->status !== PayrollRun::STATUS_PENDING) {
            throw new RuntimeException('Only a run waiting for approval can be approved or rejected.');
        }

        if ((int) $run->submitted_by === $checker->id) {
            throw new RuntimeException('The person who sent the run for approval cannot also approve it.');
        }
    }

    private function accountId(string $code, string $label): int
    {
        $id = ChartOfAccount::where('account_code', $code)->value('id');

        if (! $id) {
            throw new RuntimeException('The '.$label.' account is missing; the payroll cannot be posted.');
        }

        return (int) $id;
    }
}
