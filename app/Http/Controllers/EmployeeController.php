<?php

namespace App\Http\Controllers;

use App\Exports\EmployeeExport;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\Employee;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class EmployeeController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:employee-list', only: ['index', 'show', 'exportExcel']),
            new Middleware('can:employee-create', only: ['create', 'store']),
            new Middleware('can:employee-edit', only: ['edit', 'update', 'bulkUpdate']),
            new Middleware('can:employee-delete', only: ['destroy']),
        ];
    }

    /** Rows-per-page choices on the list ("all" shows every matching employee on one page). */
    public const PER_PAGE = [10, 50, 100, 500, 'all'];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $requested = (string) $request->get('per_page', '10');
        $perPage = in_array($requested, array_map('strval', self::PER_PAGE), true) ? $requested : '10';

        $query = $this->buildFilteredQuery();
        $employees = $query->paginate($perPage === 'all' ? max(1, $query->getEloquentBuilder()->clone()->reorder()->count()) : (int) $perPage)
            ->withQueryString();

        return view('employees.index', [
            'employees' => $employees,
            'perPage' => $perPage,
            'stats' => $this->listStats($request),
            ...$this->getFilterOptions(),
        ]);
    }

    /**
     * Headline numbers for the list. Tab counts follow the other filters
     * (search, supplier, warehouse ...) but not the Active / Inactive tab itself.
     *
     * @return array{total: int, active: int, inactive: int, with_login: int, no_warehouse: int}
     */
    private function listStats(Request $request): array
    {
        $filters = array_filter((array) $request->input('filter', []), fn ($v) => $v !== null && $v !== '');
        unset($filters['is_active']);

        $byStatus = $this->buildFilteredQuery(new Request(['filter' => $filters]))
            ->getEloquentBuilder()
            ->reorder()
            ->selectRaw('is_active, COUNT(*) as aggregate')
            ->groupBy('is_active')
            ->pluck('aggregate', 'is_active');

        return [
            'total' => (int) $byStatus->sum(),
            'active' => (int) ($byStatus[1] ?? 0),
            'inactive' => (int) ($byStatus[0] ?? 0),
            'with_login' => Employee::query()->whereNotNull('user_id')->count(),
            'no_warehouse' => Employee::query()->where('is_active', true)->whereNull('warehouse_id')->count(),
        ];
    }

    /**
     * Activate, deactivate or delete several employees from the list.
     */
    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:employees,id',
            'action' => 'required|in:activate,deactivate,delete',
        ]);

        $action = $request->action;
        abort_if($action === 'delete' && ! $request->user()->can('employee-delete'), 403);

        DB::transaction(function () use ($request, $action) {
            $employees = Employee::whereIn('id', $request->ids);

            if ($action === 'delete') {
                $employees->get()->each->delete();

                return;
            }

            $employees->update(['is_active' => $action === 'activate']);
        });

        $message = $action === 'delete' ? 'Employees deleted successfully.' : 'Employees updated successfully.';

        return redirect()->route('employees.index')->with('success', $message);
    }

    /**
     * Export filtered employees to Excel.
     */
    public function exportExcel(Request $request)
    {
        $query = $this->buildFilteredQuery()->getEloquentBuilder();

        return Excel::download(new EmployeeExport($query), 'employees.xlsx');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('employees.create', [
            'employee' => new Employee(['is_active' => true]),
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
        $employee->load(['warehouse', 'user', 'supplier', 'company', 'costCenter']);

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

            if (! $updated) {
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
     * Build the filtered query shared by index and export.
     */
    private function buildFilteredQuery(?Request $request = null): QueryBuilder
    {
        return QueryBuilder::for(
            Employee::query()->with(['warehouse', 'user', 'supplier', 'company', 'costCenter']),
            $request
        )
            ->allowedFilters([
                // One box for the list page: code, name, company, designation, phone or email.
                AllowedFilter::callback('search', function ($query, $value): void {
                    $value = trim((string) $value);
                    if ($value === '') {
                        return;
                    }
                    // LOWER() keeps the match case-insensitive on MySQL and PostgreSQL alike.
                    $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($value)).'%';
                    $query->where(function ($q) use ($like) {
                        foreach (['employee_code', 'name', 'company_name', 'designation', 'phone', 'email'] as $column) {
                            $q->orWhereRaw("LOWER({$column}) LIKE ?", [$like]);
                        }
                    });
                }),
                AllowedFilter::callback('has_login', fn ($query, $value) => $value === 'Yes' ? $query->whereNotNull('user_id') : ($value === 'No' ? $query->whereNull('user_id') : null)),
                AllowedFilter::callback('no_warehouse', fn ($query, $value) => $value === 'Yes' ? $query->whereNull('warehouse_id') : null),
                AllowedFilter::partial('employee_code'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('company_name'),
                AllowedFilter::partial('designation'),
                AllowedFilter::partial('phone'),
                AllowedFilter::partial('email'),
                AllowedFilter::partial('address'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::exact('supplier_id'),
                AllowedFilter::exact('company_id'),
                AllowedFilter::exact('cost_center_id'),
                AllowedFilter::exact('user_id'),
                AllowedFilter::exact('hire_date'),
                AllowedFilter::callback('is_active', fn ($query, $value) => $this->applyBooleanFilter($query, 'is_active', $value)),
            ])
            ->allowedSorts(['employee_code', 'name', 'designation', 'hire_date', 'created_at'])
            ->defaultSort('employee_code');
    }

    /**
     * @return array<string, mixed>
     */
    private function getFilterOptions(): array
    {
        return [
            'warehouseOptions' => Warehouse::orderBy('warehouse_name')->get(['id', 'warehouse_name']),
            'supplierOptions' => Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']),
            'companyOptions' => Company::orderBy('company_name')->get(['id', 'company_name']),
            'costCenterOptions' => CostCenter::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'userOptions' => User::orderBy('name')->get(['id', 'name']),
        ];
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
