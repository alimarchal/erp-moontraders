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
use Illuminate\Validation\Validator;

/**
 * Who is paid what: salary type, basic salary and the standing allowances and deductions of each employee. Set from the
 * salary window of the Payroll screen, for one employee or many at once.
 */
class EmployeeSalaryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:payroll-salary-manage')];
    }

    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('payroll.index', array_filter(['tab' => 'salaries', 'filter' => $request->input('filter')]));
    }

    public function edit(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorizeEmployee($request, $employee);

        return redirect()->route('payroll.index', ['tab' => 'salaries', 'filter' => ['search' => $employee->employee_code ?: $employee->name], 'edit' => $employee->id]);
    }

    /**
     * One employee: the window's rows replace the whole set-up.
     */
    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorizeEmployee($request, $employee);

        $data = $this->validated($request, single: true);
        $lines = $this->lines($data, $this->standingIds());

        DB::transaction(function () use ($employee, $data, $lines, $request) {
            EmployeeSalary::updateOrCreate(['employee_id' => $employee->id], [
                'salary_type' => $data['salary_type'], 'basic_salary' => $data['basic_salary'], 'pay_method' => $data['pay_method'],
                'notes' => $data['notes'] ?? null, 'updated_by' => $request->user()->id,
            ]);

            $employee->salaryComponents()->delete();

            foreach ($lines as $line) {
                if ($line['value'] > 0) {
                    $employee->salaryComponents()->create($line);
                }
            }
        });

        return redirect()->to($this->back($request))->with('success', 'Salary of '.$employee->name.' saved.');
    }

    /**
     * Many employees at once. A field left empty keeps what each employee already has; each row of the window is set on
     * all of them (a row with 0 takes that category off), other categories they have are kept unless "replace" is ticked.
     */
    public function bulkUpdate(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $this->validated($request, single: false);
        $lines = $this->lines($data, $this->standingIds());
        $replace = $request->boolean('replace_components');

        $employees = Employee::query()
            ->whereIn('id', $data['employee_ids'])
            ->when(! $user->hasAllSupplierAccess(), fn ($query) => $query->where('supplier_id', $user->supplier_id ?? 0))
            ->with('salary')
            ->get();

        $skipped = [];

        DB::transaction(function () use ($employees, $data, $lines, $replace, $user, &$skipped) {
            foreach ($employees as $employee) {
                $basic = $data['basic_salary'] ?? null;

                if (($basic === null || $basic === '') && ! $employee->salary) {
                    $skipped[] = $employee->name;

                    continue;
                }

                EmployeeSalary::updateOrCreate(['employee_id' => $employee->id], [
                    'salary_type' => $data['salary_type'] ?? $employee->salary?->salary_type ?? EmployeeSalary::TYPE_BASIC,
                    'basic_salary' => ($basic !== null && $basic !== '') ? $basic : $employee->salary->basic_salary,
                    'pay_method' => $data['pay_method'] ?? $employee->salary?->pay_method ?? 'cash',
                    'updated_by' => $user->id,
                ]);

                if ($replace) {
                    $employee->salaryComponents()->whereNotIn('salary_component_id', array_column($lines, 'salary_component_id'))->delete();
                }

                foreach ($lines as $line) {
                    if ($line['value'] > 0) {
                        $employee->salaryComponents()->updateOrCreate(['salary_component_id' => $line['salary_component_id']], $line);
                    } else {
                        $employee->salaryComponents()->where('salary_component_id', $line['salary_component_id'])->delete();
                    }
                }
            }
        });

        $done = $employees->count() - count($skipped);
        $message = 'Salary set-up saved for '.$done.' '.str('employee')->plural($done).'.';
        $redirect = redirect()->to($this->back($request))->with('success', $message);

        return $skipped ? $redirect->with('error', 'Skipped (no salary yet and no basic salary entered): '.implode(', ', $skipped).'.') : $redirect;
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $single): array
    {
        $standing = $this->standingIds();

        $rules = [
            'salary_type' => [$single ? 'required' : 'nullable', Rule::in([EmployeeSalary::TYPE_BASIC, EmployeeSalary::TYPE_FIXED])],
            'basic_salary' => [$single ? 'required' : 'nullable', 'numeric', 'min:0', 'max:9999999999'],
            'pay_method' => [$single ? 'required' : 'nullable', Rule::in(['cash', 'bank'])],
            'notes' => ['nullable', 'string', 'max:500'],
            'lines' => ['nullable', 'array', 'max:60'],
            'lines.*.component_id' => ['required', 'integer', Rule::in($standing)],
            'lines.*.calculation' => ['required', Rule::in(SalaryComponent::STANDING_CALCULATIONS)],
            'lines.*.value' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'lines.*.base_component_id' => ['nullable', 'required_if:lines.*.calculation,'.SalaryComponent::CALC_PERCENT_COMPONENT, 'integer', Rule::in($standing)],
            // Older forms send {component id: amount} and use each category's own calculation.
            'components' => ['nullable', 'array'],
            'components.*' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ];

        if (! $single) {
            $rules['employee_ids'] = ['required', 'array', 'min:1', 'max:1000'];
            $rules['employee_ids.*'] = ['integer', 'exists:employees,id'];
        }

        $validator = validator($request->all(), $rules, [
            'employee_ids.required' => 'Tick at least one employee first.',
            'lines.*.base_component_id.required_if' => 'Choose which allowance the percentage is of.',
        ]);

        $types = SalaryComponent::query()->whereIn('id', $standing)->pluck('type', 'id');

        $validator->after(function (Validator $validator) use ($request, $types) {
            $ids = collect((array) $request->input('lines', []))->pluck('component_id')->filter();

            if ($ids->count() !== $ids->unique()->count()) {
                $validator->errors()->add('lines', 'Each allowance or deduction can be listed only once.');
            }

            foreach ((array) $request->input('lines', []) as $line) {
                if (($line['calculation'] ?? null) === SalaryComponent::CALC_PERCENT_COMPONENT && (string) ($line['base_component_id'] ?? '') === (string) ($line['component_id'] ?? '')) {
                    $validator->errors()->add('lines', 'An allowance cannot be a percentage of itself.');
                }
                if (($line['calculation'] ?? null) === SalaryComponent::CALC_PERCENT_COMPONENT && filled($line['base_component_id'] ?? null)
                    && ((string) $line['base_component_id'] !== (string) ($line['component_id'] ?? '') && (! $ids->map(fn ($id) => (string) $id)->contains((string) $line['base_component_id']) || ($types[(int) $line['base_component_id']] ?? null) !== SalaryComponent::TYPE_EARNING))) {
                    $validator->errors()->add('lines', 'A percentage must be of another allowance listed in the same window.');
                }
                if (in_array($line['calculation'] ?? null, [SalaryComponent::CALC_PERCENT, SalaryComponent::CALC_PERCENT_COMPONENT], true) && (float) ($line['value'] ?? 0) > 1000) {
                    $validator->errors()->add('lines', 'A percentage cannot be more than 1000%.');
                }
            }
        });

        return $validator->validate();
    }

    /**
     * The window's rows as employee_salary_components attributes.
     *
     * @param  array<string, mixed>  $data
     * @param  list<int>  $standing  categories that may be set per employee
     * @return list<array{salary_component_id: int, calculation: string|null, base_component_id: int|null, value: float}>
     */
    private function lines(array $data, array $standing): array
    {
        if (array_key_exists('lines', $data) && $data['lines'] !== null) {
            return collect($data['lines'])->map(fn (array $line) => [
                'salary_component_id' => (int) $line['component_id'],
                'calculation' => $line['calculation'],
                'base_component_id' => $line['calculation'] === SalaryComponent::CALC_PERCENT_COMPONENT ? (int) $line['base_component_id'] : null,
                'value' => (float) $line['value'],
            ])->values()->all();
        }

        return collect((array) ($data['components'] ?? []))
            ->filter(fn ($value, $id) => $value !== null && $value !== '' && in_array((int) $id, $standing, true))
            ->map(fn ($value, $id) => ['salary_component_id' => (int) $id, 'calculation' => null, 'base_component_id' => null, 'value' => (float) $value])
            ->values()->all();
    }

    /**
     * @return list<int> active categories that are worked out every month
     */
    private function standingIds(): array
    {
        return SalaryComponent::query()->where('is_active', true)->where('calculation', '!=', SalaryComponent::CALC_INPUT)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function back(Request $request): string
    {
        $back = (string) $request->input('return_to', '');

        return str_starts_with($back, url('/payroll')) ? $back : route('payroll.index', ['tab' => 'salaries']);
    }

    private function authorizeEmployee(Request $request, Employee $employee): void
    {
        $user = $request->user();

        abort_unless($user->hasAllSupplierAccess() || (int) $employee->supplier_id === (int) $user->supplier_id, 404);
    }
}
