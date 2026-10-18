<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyTargetRequest;
use App\Models\CompanyTarget;
use App\Models\Employee;
use App\Models\Supplier;
use App\Services\CompanyTargetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CompanyTargetController extends Controller implements HasMiddleware
{
    private const PER_PAGE = 15;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:company-target-list', only: ['index', 'show', 'employees', 'help']),
            new Middleware('permission:company-target-create', only: ['create', 'store']),
            new Middleware('permission:company-target-edit', only: ['edit', 'update']),
            new Middleware('permission:company-target-delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'supplier_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $targets = CompanyTarget::query()
            ->with('supplier:id,supplier_name')
            ->withCount('lines')
            ->withSum('lines as total_target', 'target_amount')
            ->tap(fn ($query) => $this->scopeToUser($query))
            ->when($filters['supplier_id'] ?? null, fn ($query, $id) => $query->where('supplier_id', $id))
            ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('period_end', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('period_start', '<=', $date))
            ->orderByDesc('period_start')
            ->orderBy('supplier_id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('settings.company-targets.index', [
            'targets' => $targets,
            'suppliers' => $this->suppliersForUser(),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request, CompanyTargetService $service): View
    {
        $suppliers = $this->suppliersForUser();
        $supplierId = $this->resolveSupplierId($request->query('supplier_id', old('supplier_id')), $suppliers);
        $designation = (string) $request->query('designation', old('designation', CompanyTargetService::DEFAULT_DESIGNATION));

        return view('settings.company-targets.form', $this->formData(
            new CompanyTarget([
                'supplier_id' => $supplierId,
                'period_start' => now()->startOfMonth(),
                'period_end' => now()->endOfMonth(),
            ]),
            $suppliers,
            $service->eligibleEmployees((int) $supplierId, $designation),
            $designation,
        ));
    }

    public function store(StoreCompanyTargetRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $target = DB::transaction(function () use ($data, $request) {
            $target = CompanyTarget::create([
                'supplier_id' => $data['supplier_id'],
                'period_start' => $data['period_start'],
                'period_end' => $data['period_end'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            $this->syncLines($target, $data['lines']);

            return $target;
        });

        return redirect()->route('company-targets.show', $target)->with('success', 'Company target saved.');
    }

    public function show(CompanyTarget $companyTarget): View
    {
        $this->authorizeSheet($companyTarget);

        return view('settings.company-targets.show', [
            'target' => $companyTarget->load(['supplier:id,supplier_name', 'creator:id,name', 'lines.employee:id,name,employee_code']),
        ]);
    }

    public function edit(CompanyTarget $companyTarget, CompanyTargetService $service): View
    {
        $this->authorizeSheet($companyTarget);

        $companyTarget->load('lines.employee:id,name,employee_code,designation');

        return view('settings.company-targets.form', $this->formData(
            $companyTarget,
            $this->suppliersForUser(),
            $companyTarget->lines->map->employee->filter(),
            CompanyTargetService::DEFAULT_DESIGNATION,
        ));
    }

    public function update(StoreCompanyTargetRequest $request, CompanyTarget $companyTarget): RedirectResponse
    {
        $this->authorizeSheet($companyTarget);
        $data = $request->validated();

        DB::transaction(function () use ($companyTarget, $data, $request) {
            $companyTarget->update([
                'supplier_id' => $data['supplier_id'],
                'period_start' => $data['period_start'],
                'period_end' => $data['period_end'],
                'notes' => $data['notes'] ?? null,
                'updated_by' => $request->user()->id,
            ]);
            $this->syncLines($companyTarget, $data['lines']);
        });

        return redirect()->route('company-targets.show', $companyTarget)->with('success', 'Company target updated.');
    }

    public function destroy(CompanyTarget $companyTarget): RedirectResponse
    {
        $this->authorizeSheet($companyTarget);
        $companyTarget->delete();

        return redirect()->route('company-targets.index')->with('success', 'Company target deleted.');
    }

    /**
     * Employees and designations of a company, used by the create form to fill the rows
     * before anything is saved.
     */
    public function employees(Request $request, CompanyTargetService $service): JsonResponse
    {
        $supplierId = $this->resolveSupplierId($request->query('supplier_id'), $this->suppliersForUser());
        abort_if($supplierId === null, 403);

        $designation = (string) $request->query('designation', CompanyTargetService::DEFAULT_DESIGNATION);

        $mapRows = fn ($employees) => $employees->map(fn (Employee $employee) => [
            'id' => $employee->id,
            'name' => $employee->name,
            'code' => $employee->employee_code,
        ])->values();

        return response()->json([
            'designations' => $this->designationsOf((int) $supplierId),
            'employees' => $mapRows($service->eligibleEmployees((int) $supplierId, $designation)),
        ]);
    }

    public function help(): View
    {
        return view('settings.company-targets.help');
    }

    /**
     * @param  array<int, array{employee_id: int|string, target_label?: ?string, target_amount: numeric-string|int|float}>  $lines
     */
    private function syncLines(CompanyTarget $target, array $lines): void
    {
        $target->lines()->delete();

        foreach (array_values($lines) as $index => $line) {
            $target->lines()->create([
                'employee_id' => $line['employee_id'],
                'target_label' => filled($line['target_label'] ?? null) ? $line['target_label'] : CompanyTarget::DEFAULT_LABEL,
                'target_amount' => $line['target_amount'],
                'sort_order' => $index + 1,
            ]);
        }
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @param  Collection<int, Supplier>  $suppliers
     * @return array<string, mixed>
     */
    private function formData(CompanyTarget $target, $suppliers, $employees, string $designation): array
    {
        return [
            'target' => $target,
            'suppliers' => $suppliers,
            'designation' => $designation,
            'designations' => $this->designationsOf((int) $target->supplier_id),
            'rows' => $this->formRows($target, $employees),
            'lockedSupplier' => ! auth()->user()->hasAllSupplierAccess(),
        ];
    }

    /**
     * Every designation held in the company with how many active employees carry it, for the
     * create form's dropdown.
     *
     * @return Collection<int, array{name: string, count: int}>
     */
    private function designationsOf(int $supplierId): Collection
    {
        return Employee::query()
            ->where('supplier_id', $supplierId)
            ->whereNotNull('designation')
            ->where('designation', '!=', '')
            ->groupBy('designation')
            ->orderBy('designation')
            ->selectRaw('designation, SUM(CASE WHEN is_active THEN 1 ELSE 0 END) as active_count')
            ->get()
            ->map(fn ($row) => ['name' => $row->designation, 'count' => (int) $row->active_count])
            ->values();
    }

    /**
     * Rows the form starts with: what was just submitted (after a validation error), the saved
     * sheet, or the eligible employees at zero.
     *
     * @param  Collection<int, Employee>  $employees
     * @return Collection<int, array{employee_id: int, name: ?string, target_label: string, target_amount: string}>
     */
    private function formRows(CompanyTarget $target, $employees)
    {
        $submitted = collect(old('lines'));

        if ($submitted->isNotEmpty()) {
            $names = Employee::query()->whereIn('id', $submitted->pluck('employee_id')->filter())->pluck('name', 'id');

            return $submitted->map(fn ($line) => [
                'employee_id' => (int) ($line['employee_id'] ?? 0),
                'name' => $names[$line['employee_id'] ?? 0] ?? null,
                'target_label' => (string) ($line['target_label'] ?? CompanyTarget::DEFAULT_LABEL),
                'target_amount' => (string) ($line['target_amount'] ?? '0'),
            ])->values();
        }

        if ($target->exists) {
            return $target->lines->map(fn ($line) => [
                'employee_id' => $line->employee_id,
                'name' => $line->employee?->name,
                'target_label' => $line->target_label,
                'target_amount' => (string) $line->target_amount,
            ])->values();
        }

        return $employees->map(fn (Employee $employee) => [
            'employee_id' => $employee->id,
            'name' => $employee->name,
            'target_label' => CompanyTarget::DEFAULT_LABEL,
            'target_amount' => '0',
        ])->values();
    }

    /**
     * @return Collection<int, Supplier>
     */
    private function suppliersForUser()
    {
        $user = auth()->user();

        return Supplier::query()
            ->where('disabled', false)
            ->when(! $user->hasAllSupplierAccess(), fn ($query) => $user->supplier_id
                ? $query->whereKey($user->supplier_id)
                : $query->whereRaw('1 = 0'))
            ->orderBy('supplier_name')
            ->get(['id', 'supplier_name']);
    }

    /**
     * @param  mixed  $requested
     * @param  Collection<int, Supplier>  $suppliers
     */
    private function resolveSupplierId($requested, $suppliers): ?int
    {
        $user = auth()->user();

        if (! $user->hasAllSupplierAccess()) {
            return $user->supplier_id ? (int) $user->supplier_id : null;
        }

        return $suppliers->contains('id', (int) $requested) ? (int) $requested : null;
    }

    private function scopeToUser($query): void
    {
        $user = auth()->user();

        if (! $user->hasAllSupplierAccess()) {
            $query->where('supplier_id', $user->supplier_id ?? 0);
        }
    }

    /**
     * Another company's sheet looks like it does not exist.
     */
    private function authorizeSheet(CompanyTarget $target): void
    {
        $user = auth()->user();

        abort_unless($user->hasAllSupplierAccess() || (int) $user->supplier_id === (int) $target->supplier_id, 404);
    }
}
