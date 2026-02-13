<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeSalaryRequest;
use App\Http\Requests\UpdateEmployeeSalaryRequest;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\Supplier;
use App\Services\SalaryService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class EmployeeSalaryController extends Controller implements HasMiddleware
{
    public function __construct(private SalaryService $salaryService) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:employee-salary-list', only: ['index', 'show']),
            new Middleware('can:employee-salary-create', only: ['create', 'store']),
            new Middleware('can:employee-salary-edit', only: ['edit', 'update']),
            new Middleware('can:employee-salary-delete', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $items = QueryBuilder::for(EmployeeSalary::with(['employee', 'supplier']))
            ->allowedFilters([
                AllowedFilter::exact('employee_id'),
                AllowedFilter::exact('supplier_id'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::callback('effective_from', fn ($query, $value) => $value !== null && $value !== '' ? $query->whereDate('effective_from', '>=', $value) : null),
                AllowedFilter::callback('effective_to', fn ($query, $value) => $value !== null && $value !== '' ? $query->whereDate('effective_to', '<=', $value) : null),
            ])
            ->orderByDesc('effective_from')
            ->paginate(15)
            ->withQueryString();

        return view('employee-salaries.index', [
            'items' => $items,
            'employees' => Employee::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']),
        ]);
    }

    public function create()
    {
        return view('employee-salaries.create', $this->formData());
    }

    public function store(StoreEmployeeSalaryRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();

            // Auto-populate supplier_id from employee if not provided
            if (empty($data['supplier_id'])) {
                $employee = Employee::find($data['employee_id']);
                $data['supplier_id'] = $employee?->supplier_id;
            }

            // Calculate net_salary
            $data['net_salary'] = ($data['basic_salary'] + $data['allowances']) - $data['deductions'];

            $employeeSalary = EmployeeSalary::create($data);

            DB::commit();

            return redirect()
                ->route('employee-salaries.index')
                ->with('success', 'Employee salary created successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error creating employee salary', [
                'error' => $e->getMessage(),
                'data' => $request->validated(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to create employee salary. Please try again.');
        }
    }

    public function show(EmployeeSalary $employeeSalary)
    {
        $employeeSalary->load(['employee', 'supplier']);

        return view('employee-salaries.show', [
            'employeeSalary' => $employeeSalary,
        ]);
    }

    public function edit(EmployeeSalary $employeeSalary)
    {
        $employeeSalary->load(['employee', 'supplier']);

        return view('employee-salaries.edit', array_merge(
            ['employeeSalary' => $employeeSalary],
            $this->formData()
        ));
    }

    public function update(UpdateEmployeeSalaryRequest $request, EmployeeSalary $employeeSalary)
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();

            // Recalculate net_salary
            $data['net_salary'] = ($data['basic_salary'] + $data['allowances']) - $data['deductions'];

            $employeeSalary->update($data);

            DB::commit();

            return redirect()
                ->route('employee-salaries.index')
                ->with('success', 'Employee salary updated successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error updating employee salary', [
                'employee_salary_id' => $employeeSalary->id,
                'error' => $e->getMessage(),
                'data' => $request->validated(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to update employee salary. Please try again.');
        }
    }

    public function destroy(EmployeeSalary $employeeSalary)
    {
        try {
            $employeeSalary->delete();

            return redirect()
                ->route('employee-salaries.index')
                ->with('success', 'Employee salary deleted successfully.');
        } catch (\Throwable $e) {
            Log::error('Error deleting employee salary', [
                'employee_salary_id' => $employeeSalary->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to delete employee salary. Please try again.');
        }
    }

    /**
     * Shared dropdown data for create/edit forms.
     *
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'employees' => Employee::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']),
        ];
    }
}
