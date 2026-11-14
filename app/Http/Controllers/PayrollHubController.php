<?php

namespace App\Http\Controllers;

use App\Exports\EmployeeSalariesExport;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\PayrollAdjustment;
use App\Models\PayrollRun;
use App\Models\SalaryComponent;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PayrollService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * The one Payroll screen: monthly runs, employee salaries, incentives & adjustments and the categories, as tabs. Each tab shows
 * only to the people allowed to use it.
 */
class PayrollHubController extends Controller
{
    public const TABS = ['runs' => 'payroll-list', 'salaries' => 'payroll-salary-manage', 'adjustments' => 'payroll-adjustment-manage', 'categories' => 'payroll-salary-manage'];

    public const PER_PAGE = [25, 50, 100, 'all'];

    public function __construct(private PayrollService $payroll) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $tabs = collect(self::TABS)->filter(fn (string $permission) => $user->can($permission))->keys();
        abort_if($tabs->isEmpty(), 403);

        $tab = $tabs->contains($request->query('tab')) ? $request->query('tab') : $tabs->first();
        $filters = array_filter((array) $request->input('filter', []), fn ($value) => $value !== null && $value !== '');

        if ($tab === 'salaries' && in_array($request->query('export'), ['xlsx', 'pdf'], true)) {
            return $this->exportSalaries($request, $filters, $request->query('export'));
        }

        $data = match ($tab) {
            'runs' => $this->runsTab($request, $filters),
            'salaries' => $this->salariesTab($request, $filters),
            'adjustments' => $this->adjustmentsTab($request, $filters),
            default => $this->categoriesTab(),
        };

        return view('payroll.hub', $data + [
            'tab' => $tab,
            'tabs' => $tabs,
            'filters' => $filters,
            'counts' => $this->tabCounts($user, $tabs),
            'owners' => $user->can('payroll-create') ? $this->owners($user) : collect(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function runsTab(Request $request, array $filters): array
    {
        $base = $this->runQuery($request->user(), $filters);
        $perPage = $this->perPage($request);
        $runs = (clone $base)->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status));

        return [
            'runs' => $perPage === 'all' ? $runs->get() : $runs->paginate((int) $perPage)->withQueryString(),
            'statusCounts' => (clone $base)->reorder()->select('status')->selectRaw('COUNT(*) as total')->groupBy('status')->toBase()->pluck('total', 'status'),
            'perPage' => (string) $perPage,
            'suppliers' => $request->user()->hasAllSupplierAccess() ? Supplier::query()->orderBy('supplier_name')->get(['id', 'supplier_name']) : collect(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function salariesTab(Request $request, array $filters): array
    {
        $user = $request->user();
        $month = now()->startOfMonth();
        $perPage = $this->perPage($request);
        $query = $this->employeeQuery($user, $filters);
        $employees = $perPage === 'all' ? $query->get() : $query->paginate((int) $perPage)->withQueryString();
        $ids = collect($perPage === 'all' ? $employees : $employees->items())->pluck('id');

        $advances = EmployeeAdvance::query()->whereIn('employee_id', $ids)->where('status', EmployeeAdvance::STATUS_OPEN)
            ->orderBy('advance_date')->get(['id', 'employee_id', 'advance_date', 'amount', 'recovered_amount', 'installment_amount'])->groupBy('employee_id');

        $shorts = DB::table('sales_settlement_expenses as expenses')
            ->join('sales_settlements as settlements', 'settlements.id', '=', 'expenses.sales_settlement_id')
            ->join('chart_of_accounts as accounts', 'accounts.id', '=', 'expenses.expense_account_id')
            ->where('accounts.account_code', PayrollService::SHORT_ACCOUNT)
            ->whereIn('settlements.employee_id', $ids)
            ->where('settlements.status', 'posted')
            ->whereNull('settlements.deleted_at')
            ->whereBetween('settlements.settlement_date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->groupBy('settlements.employee_id')
            ->selectRaw('settlements.employee_id, SUM(expenses.amount) as total, COUNT(DISTINCT settlements.id) as settlements')
            ->get()->keyBy('employee_id');

        $base = $this->employeeQuery($user, []);

        return [
            'employees' => $employees,
            'perPage' => (string) $perPage,
            'components' => SalaryComponent::query()->where('is_active', true)->where('calculation', '!=', SalaryComponent::CALC_INPUT)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'type', 'calculation', 'default_value']),
            'advances' => $advances,
            'shorts' => $shorts,
            'shortMonth' => $month,
            'salaryCounts' => [
                'all' => (clone $base)->count(),
                'set' => (clone $base)->has('salary')->count(),
                'missing' => (clone $base)->doesntHave('salary')->count(),
            ],
            'designations' => Employee::query()->whereNotNull('designation')->where('designation', '!=', '')
                ->when(! $user->hasAllSupplierAccess(), fn ($query) => $query->where('supplier_id', $user->supplier_id ?? 0))
                ->distinct()->orderBy('designation')->pluck('designation'),
            'suppliers' => $user->hasAllSupplierAccess() ? Supplier::query()->orderBy('supplier_name')->get(['id', 'supplier_name']) : collect(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function adjustmentsTab(Request $request, array $filters): array
    {
        $user = $request->user();
        $month = isset($filters['month']) && preg_match('/^\d{4}-\d{2}$/', $filters['month']) ? $filters['month'] : now()->format('Y-m');
        $monthDate = Carbon::parse($month.'-01')->toDateString();
        $ownStaff = fn ($query) => $query->when(! $user->hasAllSupplierAccess(), fn ($q) => $q->where('supplier_id', $user->supplier_id ?? 0));

        $monthStart = Carbon::parse($monthDate);
        $base = PayrollAdjustment::query()->whereHas('employee', $ownStaff)->coversMonth($monthStart);
        $paidThisMonth = fn ($query) => $query->whereHas('slip.run', fn ($q) => $q->whereDate('period_month', $monthDate)->whereIn('status', PayrollRun::LOCKED_STATUSES));

        $adjustments = (clone $base)
            ->with(['employee:id,name,employee_code,designation,supplier_id', 'component:id,name,type', 'appliedRun:id,uuid,run_number', 'creator:id,name'])
            ->with(['slipLines' => fn ($query) => $paidThisMonth($query)->with('slip.run:id,uuid,run_number,period_month,status')])
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->whereHas('component', fn ($q) => $q->where('type', $type)))
            ->when(($filters['repeat'] ?? null) === 'monthly', fn ($query) => $query->where('is_recurring', true))
            ->when(($filters['repeat'] ?? null) === 'once', fn ($query) => $query->where('is_recurring', false))
            ->when(($filters['status'] ?? null) === PayrollAdjustment::STATUS_PENDING, fn ($query) => $query->where(fn ($q) => $q
                ->where(fn ($one) => $one->where('is_recurring', false)->where('status', PayrollAdjustment::STATUS_PENDING))
                ->orWhere(fn ($every) => $every->where('is_recurring', true)->whereDoesntHave('slipLines', $paidThisMonth))))
            ->when(($filters['status'] ?? null) === PayrollAdjustment::STATUS_APPLIED, fn ($query) => $query->where(fn ($q) => $q
                ->where(fn ($one) => $one->where('is_recurring', false)->where('status', PayrollAdjustment::STATUS_APPLIED))
                ->orWhere(fn ($every) => $every->where('is_recurring', true)->whereHas('slipLines', $paidThisMonth))))
            ->when($filters['component_id'] ?? null, fn ($query, $id) => $query->where('salary_component_id', $id))
            ->when($filters['search'] ?? null, fn ($query, $term) => $query->whereHas('employee', fn ($q) => $q->where(fn ($w) => $w->whereLike('name', "%{$term}%")->orWhereLike('employee_code', "%{$term}%"))))
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $totals = (clone $base)->join('salary_components', 'salary_components.id', '=', 'payroll_adjustments.salary_component_id')
            ->selectRaw('salary_components.type, SUM(payroll_adjustments.amount) as total, COUNT(*) as items')->groupBy('salary_components.type')->get()->keyBy('type');

        return [
            'adjustments' => $adjustments,
            'month' => $month,
            'monthTotals' => $totals,
            'oneOffComponents' => SalaryComponent::active()->where('calculation', SalaryComponent::CALC_INPUT)->get(['id', 'name', 'type']),
            'nextMonth' => $monthStart->copy()->addMonthNoOverflow()->format('Y-m'),
            'pickEmployees' => $this->employeeQuery($user, [])->get(['id', 'name', 'employee_code', 'designation']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function categoriesTab(): array
    {
        $components = SalaryComponent::query()->with('account:id,account_code,account_name')->orderBy('type')->orderBy('sort_order')->orderBy('name')->get();
        $usage = DB::table('employee_salary_components')->selectRaw('salary_component_id, COUNT(*) as total')->groupBy('salary_component_id')->pluck('total', 'salary_component_id');

        return [
            'categories' => $components,
            'usage' => $usage,
            'accounts' => ChartOfAccount::query()->where('is_group', false)->whereIn(DB::raw('SUBSTRING(account_code, 1, 2)'), ['11', '21', '52'])->orderBy('account_code')->get(['id', 'account_code', 'account_name']),
        ];
    }

    /**
     * Count for each tab's badge.
     *
     * @param  Collection<int, string>  $tabs
     * @return array<string, int>
     */
    private function tabCounts(User $user, Collection $tabs): array
    {
        return [
            'runs' => $tabs->contains('runs') ? PayrollRun::query()->visibleTo($user)->count() : 0,
            'salaries' => $tabs->contains('salaries') ? $this->employeeQuery($user, [])->count() : 0,
            'adjustments' => $tabs->contains('adjustments') ? PayrollAdjustment::query()->coversMonth(now())
                ->whereHas('employee', fn ($query) => $query->when(! $user->hasAllSupplierAccess(), fn ($q) => $q->where('supplier_id', $user->supplier_id ?? 0)))->count() : 0,
            'categories' => $tabs->contains('categories') ? SalaryComponent::query()->where('is_active', true)->count() : 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Employee>
     */
    private function employeeQuery(User $user, array $filters): Builder
    {
        return Employee::query()
            ->where('is_active', true)
            ->when(! $user->hasAllSupplierAccess(), fn ($query) => $query->where('supplier_id', $user->supplier_id ?? 0))
            ->with(['salary', 'salaryComponents.component:id,name,type,calculation,is_active,sort_order', 'supplier:id,supplier_name,short_name'])
            ->when($filters['search'] ?? null, fn ($query, $term) => $query->where(fn ($q) => $q->whereLike('name', "%{$term}%")->orWhereLike('employee_code', "%{$term}%")))
            ->when($filters['designation'] ?? null, fn ($query, $designation) => $query->where('designation', $designation))
            ->when($filters['supplier_id'] ?? null, fn ($query, $supplier) => $query->where('supplier_id', $supplier))
            ->when(($filters['salary'] ?? null) === 'missing', fn ($query) => $query->doesntHave('salary'))
            ->when(($filters['salary'] ?? null) === 'set', fn ($query) => $query->has('salary'))
            ->orderBy('name');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<PayrollRun>
     */
    private function runQuery(User $user, array $filters): Builder
    {
        return PayrollRun::query()
            ->visibleTo($user)
            ->with(['supplier:id,supplier_name,short_name', 'company:id,company_name,abbr', 'creator:id,name', 'checker:id,name'])
            ->withCount('slips')
            ->when($filters['month_from'] ?? null, fn ($query, $month) => $query->whereDate('period_month', '>=', Carbon::parse($month.'-01')->toDateString()))
            ->when($filters['month_to'] ?? null, fn ($query, $month) => $query->whereDate('period_month', '<=', Carbon::parse($month.'-01')->toDateString()))
            ->when($filters['search'] ?? null, fn ($query, $term) => $query->whereLike('run_number', "%{$term}%"))
            ->when($filters['owner'] ?? null, fn ($query, $owner) => $owner === 'company' ? $query->whereNull('supplier_id') : $query->where('supplier_id', (int) $owner))
            ->latest('period_month')->latest('id');
    }

    /**
     * The salary register (who earns what) as Excel or PDF, with the tab's filters.
     *
     * @param  array<string, mixed>  $filters
     */
    private function exportSalaries(Request $request, array $filters, string $format)
    {
        $employees = $this->employeeQuery($request->user(), $filters)->get();
        $components = SalaryComponent::query()->where('is_active', true)->where('calculation', '!=', SalaryComponent::CALC_INPUT)->orderBy('type')->orderBy('sort_order')->orderBy('name')->get();
        $rows = $employees->map(fn (Employee $employee) => $this->salaryRow($employee));

        if ($format === 'xlsx') {
            return Excel::download(new EmployeeSalariesExport($rows, $components), 'employee-salaries.xlsx');
        }

        return Pdf::loadView('payroll.pdf.salaries', ['rows' => $rows, 'components' => $components])
            ->setPaper('a4', $request->query('orientation') === 'portrait' ? 'portrait' : 'landscape')
            ->download('employee-salaries.pdf');
    }

    /**
     * @return array{employee: Employee, basic: float, amounts: array<int, float>, earnings: float, deductions: float, net: float}
     */
    public function salaryRow(Employee $employee): array
    {
        $basic = (float) ($employee->salary?->basic_salary ?? 0);
        $amounts = [];
        $earnings = $basic;
        $deductions = 0.0;

        foreach ($this->payroll->componentAmounts($employee, $basic) as [$component, $amount]) {
            $amounts[$component->id] = $amount;
            $component->isEarning() ? $earnings += $amount : $deductions += $amount;
        }

        return ['employee' => $employee, 'basic' => $basic, 'amounts' => $amounts, 'earnings' => round($earnings, 2), 'deductions' => round($deductions, 2), 'net' => round($earnings - $deductions, 2)];
    }

    private function perPage(Request $request): int|string
    {
        $perPage = (string) $request->query('per_page', '50');

        if (! in_array($perPage, array_map('strval', self::PER_PAGE), true)) {
            return 50;
        }

        return $perPage === 'all' ? 'all' : (int) $perPage;
    }

    /**
     * Who a new payroll can be made for: a supplier's staff, or (admins) everyone of the company.
     *
     * @return Collection<int, array{value: string, label: string}>
     */
    private function owners(User $user): Collection
    {
        $suppliers = Supplier::query()->when(! $user->hasAllSupplierAccess(), fn ($query) => $query->where('id', $user->supplier_id ?? 0))->orderBy('supplier_name')->get(['id', 'supplier_name']);
        $companies = $user->hasAllSupplierAccess() ? Company::query()->orderBy('id')->limit(1)->get(['id', 'company_name']) : collect();

        return $companies->map(fn (Company $company) => ['value' => 'company:'.$company->id, 'label' => $company->company_name.' — all employees'])
            ->concat($suppliers->map(fn (Supplier $supplier) => ['value' => 'supplier:'.$supplier->id, 'label' => $supplier->supplier_name.' staff']))
            ->values();
    }
}
