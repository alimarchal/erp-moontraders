<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\SalaryComponent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Who is paid what: salary type, basic salary and the allowances of each employee.
 */
class EmployeeSalaryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:payroll-salary-manage')];
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $filters = array_filter((array) $request->input('filter', []), fn ($value) => $value !== null && $value !== '');

        $employees = Employee::query()
            ->where('is_active', true)
            ->when(! $user->hasAllSupplierAccess(), fn ($query) => $query->where('supplier_id', $user->supplier_id ?? 0))
            ->with(['salary', 'supplier:id,supplier_name,short_name'])
            ->when($filters['search'] ?? null, fn ($query, $term) => $query->where(fn ($q) => $q->whereLike('name', "%{$term}%")->orWhereLike('employee_code', "%{$term}%")))
            ->when($filters['designation'] ?? null, fn ($query, $designation) => $query->where('designation', $designation))
            ->when(($filters['salary'] ?? null) === 'missing', fn ($query) => $query->doesntHave('salary'))
            ->when(($filters['salary'] ?? null) === 'set', fn ($query) => $query->has('salary'))
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('payroll.salaries.index', [
            'employees' => $employees,
            'filters' => $filters,
            'designations' => Employee::query()->whereNotNull('designation')->when(! $user->hasAllSupplierAccess(), fn ($query) => $query->where('supplier_id', $user->supplier_id ?? 0))->distinct()->orderBy('designation')->pluck('designation'),
        ]);
    }

    public function edit(Request $request, Employee $employee)
    {
        $this->authorizeEmployee($request, $employee);
        $employee->load(['salary', 'salaryComponents']);

        return view('payroll.salaries.edit', [
            'employee' => $employee,
            'salary' => $employee->salary ?? new EmployeeSalary(['salary_type' => 'basic', 'pay_method' => 'cash', 'basic_salary' => 0]),
            'components' => SalaryComponent::active()->where('calculation', '!=', SalaryComponent::CALC_INPUT)->get(),
            'assigned' => $employee->salaryComponents->keyBy('salary_component_id'),
            'advances' => $employee->advances()->where('status', 'open')->get(),
        ]);
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorizeEmployee($request, $employee);

        $data = $request->validate([
            'salary_type' => ['required', Rule::in([EmployeeSalary::TYPE_BASIC, EmployeeSalary::TYPE_FIXED])],
            'basic_salary' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'pay_method' => ['required', Rule::in(['cash', 'bank'])],
            'notes' => ['nullable', 'string', 'max:500'],
            'components' => ['nullable', 'array'],
            'components.*' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ]);

        DB::transaction(function () use ($employee, $data, $request) {
            EmployeeSalary::updateOrCreate(['employee_id' => $employee->id], [
                'salary_type' => $data['salary_type'], 'basic_salary' => $data['basic_salary'], 'pay_method' => $data['pay_method'],
                'notes' => $data['notes'] ?? null, 'updated_by' => $request->user()->id,
            ]);

            $valid = SalaryComponent::query()->where('calculation', '!=', SalaryComponent::CALC_INPUT)->pluck('id')->all();
            $employee->salaryComponents()->delete();

            foreach ((array) ($data['components'] ?? []) as $componentId => $value) {
                if (in_array((int) $componentId, $valid, true) && (float) $value > 0) {
                    $employee->salaryComponents()->create(['salary_component_id' => $componentId, 'value' => $value]);
                }
            }
        });

        return redirect()->route('payroll.salaries.index')->with('success', 'Salary of '.$employee->name.' saved.');
    }

    private function authorizeEmployee(Request $request, Employee $employee): void
    {
        $user = $request->user();

        abort_unless($user->hasAllSupplierAccess() || (int) $employee->supplier_id === (int) $user->supplier_id, 404);
    }
}
