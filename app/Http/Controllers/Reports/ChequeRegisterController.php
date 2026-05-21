<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\SalesSettlementCheque;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ChequeRegisterController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-audit-cheque-register', only: ['index']),
            new Middleware('can:report-audit-cheque-register-manage', only: ['updateStatus']),
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
        $customerId = $request->input('filter.customer_id');
        $statuses = array_filter((array) $request->input('filter.status', []));
        $chequeDateFrom = $request->input('filter.cheque_date_from');
        $chequeDateTo = $request->input('filter.cheque_date_to');
        $entryDateFrom = $request->input('filter.entry_date_from');
        $entryDateTo = $request->input('filter.entry_date_to');
        $bankName = $request->input('filter.bank_name');
        $chequeNumber = $request->input('filter.cheque_number');

        $allowedSorts = [
            'cheque_date' => 'sales_settlement_cheques.cheque_date',
            'created_at' => 'sales_settlement_cheques.created_at',
            'amount' => 'sales_settlement_cheques.amount',
        ];
        $sortBy = $request->input('sort', 'created_at');
        $sortBy = array_key_exists($sortBy, $allowedSorts) ? $sortBy : 'created_at';
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
        $customerIds = SalesSettlementCheque::query()
            ->join('sales_settlements', 'sales_settlements.id', '=', 'sales_settlement_cheques.sales_settlement_id')
            ->when($supplierId, fn ($query) => $query->where('sales_settlements.supplier_id', $supplierId))
            ->when(! $canViewAllSuppliers && ! $supplierId, fn ($query) => $query->whereRaw('1 = 0'))
            ->whereNotNull('sales_settlement_cheques.customer_id')
            ->distinct()
            ->pluck('sales_settlement_cheques.customer_id');
        $customers = Customer::query()
            ->whereIn('id', $customerIds)
            ->orderBy('customer_name')
            ->get(['id', 'customer_name']);

        $query = SalesSettlementCheque::query()
            ->join('sales_settlements', 'sales_settlements.id', '=', 'sales_settlement_cheques.sales_settlement_id')
            ->leftJoin('employees', 'employees.id', '=', 'sales_settlements.employee_id')
            ->leftJoin('customers', 'customers.id', '=', 'sales_settlement_cheques.customer_id')
            ->select([
                'sales_settlement_cheques.*',
                'employees.name as employee_name',
                'customers.customer_name',
                'customers.address as customer_address',
            ])
            ->when($supplierId, fn ($q) => $q->where('sales_settlements.supplier_id', $supplierId))
            ->when(! $canViewAllSuppliers && ! $supplierId, fn ($q) => $q->whereRaw('1 = 0'))
            ->when($employeeId, fn ($q) => $q->where('sales_settlements.employee_id', $employeeId))
            ->when($customerId, fn ($q) => $q->where('sales_settlement_cheques.customer_id', $customerId))
            ->when($statuses, fn ($q) => $q->whereIn('sales_settlement_cheques.status', $statuses))
            ->when($chequeDateFrom, fn ($q) => $q->where('sales_settlement_cheques.cheque_date', '>=', $chequeDateFrom))
            ->when($chequeDateTo, fn ($q) => $q->where('sales_settlement_cheques.cheque_date', '<=', $chequeDateTo))
            ->when($entryDateFrom, fn ($q) => $q->where('sales_settlement_cheques.created_at', '>=', $entryDateFrom))
            ->when($entryDateTo, fn ($q) => $q->where('sales_settlement_cheques.created_at', '<=', $entryDateTo.' 23:59:59'))
            ->when($bankName, fn ($q) => $q->where('sales_settlement_cheques.bank_name', 'like', '%'.$bankName.'%'))
            ->when($chequeNumber, fn ($q) => $q->where('sales_settlement_cheques.cheque_number', 'like', '%'.$chequeNumber.'%'))
            ->orderBy($allowedSorts[$sortBy], $sortDir)
            ->orderBy('sales_settlement_cheques.id', $sortDir);

        $totalAmount = (clone $query)->sum('sales_settlement_cheques.amount');

        if ($perPage === 'all') {
            $allItems = $query->get();
            $cheques = new LengthAwarePaginator(
                $allItems,
                $allItems->count(),
                $allItems->count() ?: 1,
                1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            $cheques = $query->paginate((int) $perPage)->withQueryString();
        }

        return view('reports.cheque-register.index', [
            'cheques' => $cheques,
            'suppliers' => $suppliers,
            'employees' => $employees,
            'customers' => $customers,
            'totalAmount' => $totalAmount,
            'perPage' => $perPage,
            'selectedSupplierId' => $supplierId,
            'selectedEmployeeId' => $employeeId,
            'selectedCustomerId' => $customerId,
            'selectedStatuses' => $statuses,
            'chequeDateFrom' => $chequeDateFrom,
            'chequeDateTo' => $chequeDateTo,
            'entryDateFrom' => $entryDateFrom,
            'entryDateTo' => $entryDateTo,
            'bankName' => $bankName,
            'chequeNumber' => $chequeNumber,
            'availableStatuses' => ['pending', 'cleared', 'bounced', 'cancelled'],
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'canViewAllSuppliers' => $canViewAllSuppliers,
        ]);
    }

    public function updateStatus(Request $request, SalesSettlementCheque $cheque)
    {
        $cheque->loadMissing('salesSettlement');
        $this->authorizeChequeAccess($cheque);

        $validated = $request->validate([
            'status' => ['required', 'in:pending,cleared,bounced,cancelled'],
            'cleared_date' => ['nullable', 'date'],
        ]);

        $cheque->update([
            'status' => $validated['status'],
            'cleared_date' => $validated['cleared_date'] ?? null,
            'status_updated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Cheque status updated successfully.');
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

    private function authorizeChequeAccess(SalesSettlementCheque $cheque): void
    {
        if ($this->canViewAllSuppliers()) {
            return;
        }

        $userSupplierId = $this->getUserSupplierScope();

        if (! $userSupplierId || (int) $cheque->salesSettlement?->supplier_id !== $userSupplierId) {
            abort(403, 'You do not have permission to access this cheque.');
        }
    }
}
