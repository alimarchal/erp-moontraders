<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SalesSettlementAmrLiquid;
use App\Models\SalesSettlementAmrPowder;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AmrDisposeRegisterController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-audit-amr-dispose-register', only: ['index']),
            new Middleware('can:report-audit-amr-dispose-register-manage', only: ['updateDisposed', 'bulkUpdateDisposed']),
        ];
    }

    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 50);
        $perPage = in_array($perPage, [10, 25, 50, 100, 250, 'all']) ? $perPage : 50;
        $canViewAllSuppliers = $this->canViewAllSuppliers();
        $userSupplierId = $this->getUserSupplierScope();

        $requestedSupplierId = $request->input('filter.supplier_id');
        if ($requestedSupplierId && ! $canViewAllSuppliers && (int) $requestedSupplierId !== $userSupplierId) {
            abort(403, 'You do not have permission to filter by this supplier.');
        }

        $supplierId = $userSupplierId ?? $requestedSupplierId;
        $employeeId = $request->input('filter.employee_id');
        $type = $request->input('filter.type', 'both');
        $type = in_array($type, ['liquids', 'powders', 'both']) ? $type : 'both';
        $isDisposed = $request->input('filter.is_disposed', '0');
        $isDisposed = in_array($isDisposed, ['0', '1', 'all']) ? $isDisposed : '0';
        $settlementDateFrom = $request->input('filter.settlement_date_from');
        $settlementDateTo = $request->input('filter.settlement_date_to');
        $disposedAtFrom = $request->input('filter.disposed_at_from');
        $disposedAtTo = $request->input('filter.disposed_at_to');
        $productName = $request->input('filter.product_name');

        $allowedSorts = [
            'settlement_date' => 'settlement_date',
            'disposed_at' => 'disposed_at',
            'amount' => 'amount',
            'quantity' => 'quantity',
        ];
        $sortBy = $request->input('sort', 'settlement_date');
        $sortBy = array_key_exists($sortBy, $allowedSorts) ? $sortBy : 'settlement_date';
        $sortDir = $request->input('direction', 'desc');
        $sortDir = in_array($sortDir, ['asc', 'desc']) ? $sortDir : 'desc';

        if ($employeeId && ! $canViewAllSuppliers) {
            $isAllowedEmployee = Employee::query()
                ->where('id', $employeeId)
                ->where('supplier_id', $supplierId)
                ->exists();

            if (! $isAllowedEmployee) {
                abort(403, 'You do not have permission to filter by this salesman.');
            }
        }

        $suppliers = Supplier::query()
            ->when($supplierId, fn ($query) => $query->where('id', $supplierId))
            ->when(! $canViewAllSuppliers && ! $supplierId, fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('supplier_name')
            ->get(['id', 'supplier_name']);
        $employees = Employee::query()
            ->where('is_active', true)
            ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
            ->when(! $canViewAllSuppliers && ! $supplierId, fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('name')
            ->get(['id', 'name']);

        $buildQuery = function (string $table, string $typeLabel) use (
            $supplierId, $canViewAllSuppliers, $employeeId, $isDisposed, $settlementDateFrom, $settlementDateTo,
            $disposedAtFrom, $disposedAtTo, $productName
        ) {
            return DB::table($table)
                ->join('sales_settlements', 'sales_settlements.id', '=', $table.'.sales_settlement_id')
                ->join('products', 'products.id', '=', $table.'.product_id')
                ->leftJoin('employees', 'employees.id', '=', 'sales_settlements.employee_id')
                ->select([
                    $table.'.id',
                    $table.'.sales_settlement_id',
                    $table.'.product_id',
                    $table.'.batch_code',
                    $table.'.quantity',
                    $table.'.amount',
                    $table.'.notes',
                    $table.'.is_disposed',
                    $table.'.disposed_at',
                    'sales_settlements.settlement_number',
                    'sales_settlements.settlement_date',
                    'sales_settlements.supplier_id',
                    'products.product_name',
                    'employees.name as employee_name',
                    DB::raw("'{$typeLabel}' as record_type"),
                    DB::raw("'{$table}' as record_table"),
                ])
                ->when($supplierId, fn ($q) => $q->where('sales_settlements.supplier_id', $supplierId))
                ->when(! $canViewAllSuppliers && ! $supplierId, fn ($q) => $q->whereRaw('1 = 0'))
                ->when($employeeId, fn ($q) => $q->where('sales_settlements.employee_id', $employeeId))
                ->when($isDisposed !== 'all', fn ($q) => $q->where($table.'.is_disposed', (bool) $isDisposed))
                ->when($settlementDateFrom, fn ($q) => $q->where('sales_settlements.settlement_date', '>=', $settlementDateFrom))
                ->when($settlementDateTo, fn ($q) => $q->where('sales_settlements.settlement_date', '<=', $settlementDateTo))
                ->when($disposedAtFrom, fn ($q) => $q->where($table.'.disposed_at', '>=', $disposedAtFrom))
                ->when($disposedAtTo, fn ($q) => $q->where($table.'.disposed_at', '<=', $disposedAtTo.' 23:59:59'))
                ->when($productName, fn ($q) => $q->where('products.product_name', 'like', '%'.$productName.'%'));
        };

        if ($type === 'liquids') {
            $query = $buildQuery('sales_settlement_amr_liquids', 'Liquid');
        } elseif ($type === 'powders') {
            $query = $buildQuery('sales_settlement_amr_powders', 'Powder');
        } else {
            $liquidQuery = $buildQuery('sales_settlement_amr_liquids', 'Liquid');
            $powderQuery = $buildQuery('sales_settlement_amr_powders', 'Powder');
            $query = $liquidQuery->union($powderQuery);
        }

        $query->orderBy($allowedSorts[$sortBy], $sortDir)->orderBy('id', $sortDir);

        $totalAmount = (clone $query)->sum('amount');
        $totalQuantity = (clone $query)->sum('quantity');

        if ($perPage === 'all') {
            $allItems = $query->get();
            $records = new LengthAwarePaginator(
                $allItems,
                $allItems->count(),
                $allItems->count() ?: 1,
                1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            $records = $query->paginate((int) $perPage)->withQueryString();
        }

        return view('reports.amr-dispose-register.index', [
            'records' => $records,
            'suppliers' => $suppliers,
            'employees' => $employees,
            'totalAmount' => $totalAmount,
            'totalQuantity' => $totalQuantity,
            'perPage' => $perPage,
            'selectedSupplierId' => $supplierId,
            'selectedEmployeeId' => $employeeId,
            'selectedType' => $type,
            'selectedIsDisposed' => $isDisposed,
            'settlementDateFrom' => $settlementDateFrom,
            'settlementDateTo' => $settlementDateTo,
            'disposedAtFrom' => $disposedAtFrom,
            'disposedAtTo' => $disposedAtTo,
            'productName' => $productName,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'canViewAllSuppliers' => $canViewAllSuppliers,
        ]);
    }

    public function updateDisposed(Request $request, string $type, int $id)
    {
        abort_unless(in_array($type, ['liquid', 'powder']), 404);

        $validated = $request->validate([
            'is_disposed' => ['required', 'boolean'],
        ]);

        $model = $type === 'liquid' ? SalesSettlementAmrLiquid::class : SalesSettlementAmrPowder::class;
        $record = $model::with('salesSettlement')->findOrFail($id);
        $this->authorizeAmrRecordAccess($record);

        $record->update(['is_disposed' => $validated['is_disposed']]);

        return redirect()->back()->with('success', 'Dispose status updated successfully.');
    }

    public function bulkUpdateDisposed(Request $request)
    {
        $validated = $request->validate([
            'is_disposed' => ['required', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.type' => ['required', 'in:liquid,powder'],
            'items.*.id' => ['required', 'integer'],
        ]);

        $liquids = collect($validated['items'])->where('type', 'liquid')->pluck('id');
        $powders = collect($validated['items'])->where('type', 'powder')->pluck('id');
        $this->authorizeBulkAmrAccess(SalesSettlementAmrLiquid::class, $liquids);
        $this->authorizeBulkAmrAccess(SalesSettlementAmrPowder::class, $powders);

        $isDisposed = (bool) $validated['is_disposed'];
        $disposedAt = $isDisposed ? now() : null;

        if ($liquids->isNotEmpty()) {
            SalesSettlementAmrLiquid::whereIn('id', $liquids)->update([
                'is_disposed' => $isDisposed,
                'disposed_at' => $disposedAt,
            ]);
        }

        if ($powders->isNotEmpty()) {
            SalesSettlementAmrPowder::whereIn('id', $powders)->update([
                'is_disposed' => $isDisposed,
                'disposed_at' => $disposedAt,
            ]);
        }

        $count = $liquids->count() + $powders->count();

        return redirect()->back()->with('success', "{$count} record(s) updated successfully.");
    }

    private function getUserSupplierScope(): ?int
    {
        $user = auth()->user();

        if ($this->canViewAllSuppliers()) {
            return null;
        }

        return $user->supplier_id ? (int) $user->supplier_id : null;
    }

    private function canViewAllSuppliers(): bool
    {
        $user = auth()->user();

        return $user->is_super_admin === 'Yes'
            || $user->hasRole('super-admin')
            || $user->hasRole('admin');
    }

    private function authorizeAmrRecordAccess(SalesSettlementAmrLiquid|SalesSettlementAmrPowder $record): void
    {
        if ($this->canViewAllSuppliers()) {
            return;
        }

        $userSupplierId = $this->getUserSupplierScope();

        if (! $userSupplierId || (int) $record->salesSettlement?->supplier_id !== $userSupplierId) {
            abort(403, 'You do not have permission to access this AMR record.');
        }
    }

    private function authorizeBulkAmrAccess(string $model, Collection $ids): void
    {
        if ($this->canViewAllSuppliers() || $ids->isEmpty()) {
            return;
        }

        $userSupplierId = $this->getUserSupplierScope();
        if (! $userSupplierId) {
            abort(403, 'You do not have permission to access these AMR records.');
        }

        $uniqueIds = $ids->unique()->values();
        $allowedCount = $model::query()
            ->whereIn('id', $uniqueIds)
            ->whereHas('salesSettlement', fn ($query) => $query->where('supplier_id', $userSupplierId))
            ->count();

        if ($allowedCount !== $uniqueIds->count()) {
            abort(403, 'You do not have permission to access these AMR records.');
        }
    }
}
