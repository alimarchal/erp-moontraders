<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Supplier;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Customer Balances (reports/customer-balances): one row per customer with what they owe across every salesman
 * account (debit minus credit), as on a date, with the last credit sale and the last recovery. Admins see every
 * company; anyone else sees only the accounts of their own company's salesmen.
 */
class CustomerBalancesController extends Controller implements HasMiddleware
{
    /** @var array<int, int|string> */
    public const PER_PAGE = [25, 50, 100, 250, 'all'];

    public static function middleware(): array
    {
        return [
            new Middleware('can:report-audit-customer-balances'),
        ];
    }

    public function index(Request $request): View
    {
        $canViewAllSuppliers = $this->canViewAllSuppliers();
        $userSupplierId = auth()->user()->supplier_id ? (int) auth()->user()->supplier_id : null;
        $requestedSupplierId = $request->filled('filter.supplier_id') ? (int) $request->input('filter.supplier_id') : null;

        if ($requestedSupplierId && ! $canViewAllSuppliers && $requestedSupplierId !== $userSupplierId) {
            abort(403, 'You do not have permission to see another company.');
        }

        $supplierId = $canViewAllSuppliers ? $requestedSupplierId : $userSupplierId;
        $asOf = rescue(fn () => $request->date('filter.as_of')?->toDateString(), null, false) ?? now()->toDateString();
        $status = (string) $request->input('filter.status', 'owes');
        $perPage = $this->resolvePerPage($request);
        $sort = (string) $request->input('sort', $status === 'advance' ? 'balance' : '-balance');

        $balances = $this->balancesByCustomer($asOf, $supplierId, $request->integer('filter.employee_id') ?: null, ! $canViewAllSuppliers && ! $userSupplierId);

        $customers = Customer::query()
            ->joinSub($balances, 'b', 'b.customer_id', '=', 'customers.id')
            ->with('salesRep:id,name')
            ->select('customers.*', 'b.debits', 'b.credits', 'b.balance', 'b.accounts', 'b.last_sale', 'b.last_recovery')
            ->when($status === 'owes', fn ($query) => $query->where('b.balance', '>', 0.004))
            ->when($status === 'advance', fn ($query) => $query->where('b.balance', '<', -0.004))
            ->when($status === 'clear', fn ($query) => $query->whereBetween('b.balance', [-0.004, 0.004]))
            ->when($request->filled('filter.search'), function ($query) use ($request) {
                $term = '%'.trim((string) $request->input('filter.search')).'%';
                $query->where(fn ($q) => $q->where('customers.customer_name', 'like', $term)
                    ->orWhere('customers.customer_code', 'like', $term)
                    ->orWhere('customers.business_name', 'like', $term)
                    ->orWhere('customers.phone', 'like', $term)
                    ->orWhere('customers.sub_locality', 'like', $term)
                    ->orWhere('customers.city', 'like', $term));
            })
            ->when($request->filled('filter.channel_type'), fn ($query) => $query->where('customers.channel_type', $request->input('filter.channel_type')))
            ->when($request->filled('filter.city'), fn ($query) => $query->where('customers.city', 'like', '%'.$request->input('filter.city').'%'))
            ->when($request->filled('filter.sub_locality'), fn ($query) => $query->where('customers.sub_locality', 'like', '%'.$request->input('filter.sub_locality').'%'))
            ->when($request->filled('filter.min_balance'), fn ($query) => $query->where('b.balance', '>=', (float) $request->input('filter.min_balance')));

        $totals = DB::query()->fromSub((clone $customers)->reorder()->select('b.balance'), 'rows')
            ->selectRaw('COUNT(*) as customers, COALESCE(SUM(CASE WHEN balance > 0 THEN balance ELSE 0 END), 0) as owed, COALESCE(SUM(CASE WHEN balance < 0 THEN balance ELSE 0 END), 0) as advance')
            ->first();

        match (ltrim($sort, '-')) {
            'customer_name' => $customers->orderBy('customers.customer_name', str_starts_with($sort, '-') ? 'desc' : 'asc'),
            'last_recovery' => $customers->orderByRaw('b.last_recovery IS NULL DESC')->orderBy('b.last_recovery', str_starts_with($sort, '-') ? 'desc' : 'asc'),
            default => $customers->orderBy('b.balance', $sort === 'balance' ? 'asc' : 'desc'),
        };
        $customers->orderBy('customers.id');

        $rows = $perPage === 'all'
            ? new LengthAwarePaginator($all = $customers->get(), $all->count(), max(1, $all->count()), 1, ['path' => $request->url(), 'query' => $request->query()])
            : $customers->paginate($perPage)->withQueryString();

        return view('reports.customer-balances.index', [
            'customers' => $rows,
            'totals' => [
                'customers' => (int) $totals->customers,
                'owed' => (float) $totals->owed,
                'advance' => (float) $totals->advance,
                'net' => (float) $totals->owed + (float) $totals->advance,
            ],
            'asOf' => $asOf,
            'status' => $status,
            'sort' => $sort,
            'perPage' => $perPage,
            'supplierId' => $supplierId,
            'canViewAllSuppliers' => $canViewAllSuppliers,
            'suppliers' => Supplier::query()
                ->when(! $canViewAllSuppliers, fn ($query) => $query->whereKey($userSupplierId ?? 0))
                ->orderBy('supplier_name')
                ->get(['id', 'supplier_name']),
            'employees' => Employee::query()
                ->whereHas('customerAccounts')
                ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
                ->when(! $canViewAllSuppliers && ! $userSupplierId, fn ($query) => $query->whereRaw('1 = 0'))
                ->orderBy('name')
                ->get(['id', 'name']),
            'channelTypes' => Customer::CHANNEL_TYPES,
        ]);
    }

    /**
     * Debit, credit and balance per customer from the salesman accounts, up to and including the date.
     */
    private function balancesByCustomer(string $asOf, ?int $supplierId, ?int $employeeId, bool $seesNothing): QueryBuilder
    {
        return DB::table('customer_employee_account_transactions as t')
            ->join('customer_employee_accounts as a', 'a.id', '=', 't.customer_employee_account_id')
            ->join('employees as e', 'e.id', '=', 'a.employee_id')
            ->whereNull('t.deleted_at')
            ->whereDate('t.transaction_date', '<=', $asOf)
            ->when($supplierId, fn ($query) => $query->where('e.supplier_id', $supplierId))
            ->when($employeeId, fn ($query) => $query->where('a.employee_id', $employeeId))
            ->when($seesNothing, fn ($query) => $query->whereRaw('1 = 0'))
            ->groupBy('a.customer_id')
            ->selectRaw("a.customer_id,
                COALESCE(SUM(t.debit), 0) as debits,
                COALESCE(SUM(t.credit), 0) as credits,
                COALESCE(SUM(t.debit), 0) - COALESCE(SUM(t.credit), 0) as balance,
                COUNT(DISTINCT a.id) as accounts,
                MAX(CASE WHEN t.transaction_type = 'credit_sale' THEN t.transaction_date END) as last_sale,
                MAX(CASE WHEN t.transaction_type = 'recovery' THEN t.transaction_date END) as last_recovery");
    }

    private function resolvePerPage(Request $request): int|string
    {
        $perPage = (string) $request->input('per_page', 50);

        if ($perPage === 'all') {
            return 'all';
        }

        return in_array((int) $perPage, self::PER_PAGE, true) ? (int) $perPage : 50;
    }

    private function canViewAllSuppliers(): bool
    {
        $user = auth()->user();

        return $user->is_super_admin === 'Yes' || $user->hasRole(['super-admin', 'admin']);
    }
}
