<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Supplier;
use App\Models\Company;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $employees = QueryBuilder::for(
            Employee::query()->with(['warehouse', 'user', 'supplier', 'company'])
        )
            ->allowedFilters([
                AllowedFilter::partial('employee_code'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('company_name'),
                AllowedFilter::partial('designation'),
                AllowedFilter::partial('phone'),
                AllowedFilter::partial('email'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::exact('supplier_id'),
                AllowedFilter::exact('company_id'),
                AllowedFilter::callback('is_active', fn($query, $value) => $this->applyBooleanFilter($query, 'is_active', $value)),
            ])
            ->orderBy('employee_code')
            ->paginate(15)
            ->withQueryString();

        $warehouseOptions = Warehouse::orderBy('warehouse_name')->get(['id', 'warehouse_name']);
        $supplierOptions = Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']);
        $companyOptions = Company::orderBy('company_name')->get(['id', 'company_name']);

        return view('employees.index', [
            'employees' => $employees,
            'warehouseOptions' => $warehouseOptions,
            'supplierOptions' => $supplierOptions,
            'companyOptions' => $companyOptions,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('employees.create', [
            'warehouseOptions' => Warehouse::orderBy('warehouse_name')->get(['id', 'warehouse_name']),
            'userOptions' => User::orderBy('name')->get(['id', 'name']),
            'supplierOptions' => Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']),
            'companyOptions' => Company::orderBy('company_name')->get(['id', 'company_name']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEmployeeRequest $request)
    {
        DB::beginTransaction();

        try {
            $employee = Employee::create($request->validated());

            DB::commit();

            return redirect()
                ->route('employees.index')
                ->with('success', "Employee '{$employee->employee_code}' created successfully.");
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Database error creating employee', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            $message = 'Unable to create employee. Please review the input and try again.';
            if ($e->getCode() === '23000') {
                if (str_contains($e->getMessage(), 'employees_employee_code_unique')) {
                    $message = 'An employee with this code already exists.';
                } elseif (str_contains($e->getMessage(), 'employees_email_unique')) {
                    $message = 'An employee with this email already exists.';
                }
            }

            return back()
                ->withInput()
                ->with('error', [
                    'message' => $message,
                    'db' => $e->getMessage(),
                ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error creating employee', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to create employee. Please try again.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Employee $employee)
    {
        $employee->load(['warehouse', 'user', 'salaries', 'supplier', 'company']);

        return view('employees.show', [
            'employee' => $employee,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Employee $employee)
    {
        $employee->load(['warehouse', 'user', 'supplier', 'company']);

        return view('employees.edit', [
            'employee' => $employee,
            'warehouseOptions' => Warehouse::orderBy('warehouse_name')->get(['id', 'warehouse_name']),
            'userOptions' => User::orderBy('name')->get(['id', 'name']),
            'supplierOptions' => Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']),
            'companyOptions' => Company::orderBy('company_name')->get(['id', 'company_name']),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee)
    {
        DB::beginTransaction();

        try {
            $updated = $employee->update($request->validated());

            if (!$updated) {
                DB::rollBack();

                return back()
                    ->withInput()
                    ->with('info', 'No changes were made to the employee record.');
            }

            DB::commit();

            return redirect()
                ->route('employees.index')
                ->with('success', "Employee '{$employee->employee_code}' updated successfully.");
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Database error updating employee', [
                'employee_id' => $employee->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            $message = 'Unable to update employee. Please review the input and try again.';
            if ($e->getCode() === '23000') {
                if (str_contains($e->getMessage(), 'employees_employee_code_unique')) {
                    $message = 'An employee with this code already exists.';
                } elseif (str_contains($e->getMessage(), 'employees_email_unique')) {
                    $message = 'An employee with this email already exists.';
                }
            }

            return back()
                ->withInput()
                ->with('error', [
                    'message' => $message,
                    'db' => $e->getMessage(),
                ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error updating employee', [
                'employee_id' => $employee->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to update employee. Please try again.');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Employee $employee)
    {
        if ($employee->salaries()->exists()) {
            return back()->with('error', 'Unable to delete employee while salary records exist. Please remove related salary history first.');
        }

        try {
            $code = $employee->employee_code;
            $employee->delete();

            return redirect()
                ->route('employees.index')
                ->with('success', "Employee '{$code}' deleted successfully.");
        } catch (\Throwable $e) {
            Log::error('Error deleting employee', [
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to delete employee. Please try again.');
        }
    }

    /**
     * Apply a boolean filter to the query when applicable.
     */
    protected function applyBooleanFilter($query, string $column, $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $flag = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($flag === null) {
            return;
        }

        $query->where($column, $flag);
    }
}
