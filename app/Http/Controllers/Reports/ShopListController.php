<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Supplier;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shop Directory (reports/shop-list): every shop with its contact, tax and area details and what it means to the
 * business: balance across the salesman accounts, credit limit, last sale and last recovery. The user picks the
 * columns; format=pdf downloads the same list as an A4 PDF (portrait unless orientation=landscape). A user tied to
 * one company sees balances from that company's salesmen only.
 */
class ShopListController extends Controller implements HasMiddleware
{
    /** @var list<int|string> */
    public const PER_PAGE_OPTIONS = [50, 100, 250, 500, 1000, 'all'];

    public const DEFAULT_PER_PAGE = 100;

    /**
     * Columns the user can show or hide: key => [label, shown by default].
     *
     * @var array<string, array{0: string, 1: bool}>
     */
    public const COLUMNS = [
        'area' => ['Area', true],
        'channel' => ['Channel', true],
        'phone' => ['Phone', true],
        'balance' => ['Balance', true],
        'last_sale' => ['Last sale', true],
        'status' => ['Status', true],
        'address' => ['Address', false],
        'owner_cnic' => ['Owner CNIC', false],
        'ntn' => ['NTN', false],
        'it_status' => ['Filer', false],
        'sales_rep' => ['Sales rep', false],
        'credit_limit' => ['Credit limit', false],
        'last_recovery' => ['Last recovery', false],
        'accounts' => ['Salesmen', false],
        'business_name' => ['Business name', false],
        'email' => ['Email', false],
        'payment_terms' => ['Payment terms', false],
    ];

    /** @var array<string, string> */
    public const SORTS = [
        'customer_code' => 'Code', 'customer_name' => 'Name', 'area' => 'Area', 'balance' => 'Balance', 'last_sale' => 'Last sale',
    ];

    public static function middleware(): array
    {
        return [
            new Middleware('can:report-sales-shop-list'),
        ];
    }

    public function index(Request $request): View|Response
    {
        [$supplierId, $canViewAllSuppliers] = $this->supplierScope($request);
        $filters = $this->filters($request);
        $sort = array_key_exists(ltrim((string) $request->input('sort', 'customer_code'), '-'), self::SORTS) ? (string) $request->input('sort', 'customer_code') : 'customer_code';
        $perPage = $this->perPage($request);
        $columns = $this->columns($request);

        $query = $this->filteredQuery($filters, $supplierId);
        $stats = $this->stats(Arr::except($filters, 'is_active'), $supplierId);
        $totals = DB::query()->fromSub((clone $query)->reorder()->select('customers.id', 'b.balance'), 'rows')
            ->selectRaw('COUNT(*) as shops, COALESCE(SUM(CASE WHEN balance > 0 THEN balance ELSE 0 END), 0) as owed, COALESCE(SUM(CASE WHEN balance < 0 THEN balance ELSE 0 END), 0) as advance')
            ->first();
        $this->applySort($query, $sort);

        if ($request->query('format') === 'pdf') {
            $orientation = $request->query('orientation') === 'landscape' ? 'landscape' : 'portrait';

            return Pdf::loadView('reports.shop-list.pdf', [
                'customers' => $query->get(),
                'columns' => $columns,
                'filterSummary' => $this->filterSummary($filters, $supplierId),
                'totals' => $totals,
                'orientation' => $orientation,
            ])->setPaper('a4', $orientation)->download('shop-directory-'.now()->format('Y-m-d').'.pdf');
        }

        $customers = $perPage === 'all'
            ? new LengthAwarePaginator($all = $query->get(), $all->count(), max(1, $all->count()), 1, ['path' => $request->url(), 'query' => $request->query()])
            : $query->paginate($perPage)->withQueryString();

        return view('reports.shop-list.index', [
            'customers' => $customers,
            'filters' => $filters,
            'stats' => $stats,
            'totals' => $totals,
            'sort' => $sort,
            'perPage' => $perPage,
            'columns' => $columns,
            'filterSummary' => $this->filterSummary($filters, $supplierId),
            'supplierId' => $supplierId,
            'canViewAllSuppliers' => $canViewAllSuppliers,
            'channelTypes' => Customer::CHANNEL_TYPES,
            'customerCategories' => Customer::CUSTOMER_CATEGORIES,
            'salesRepOptions' => User::query()->whereIn('id', Customer::query()->whereNotNull('sales_rep_id')->select('sales_rep_id'))->orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::query()->when(! $canViewAllSuppliers, fn ($q) => $q->whereKey($supplierId ?? 0))->orderBy('supplier_name')->get(['id', 'supplier_name']),
            'employees' => Employee::query()->whereHas('customerAccounts')->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Quick look at one shop for the pop-up: details, credit and each salesman account with its balance.
     */
    public function show(Request $request, Customer $customer): View
    {
        [$supplierId] = $this->supplierScope($request);
        $customer->load('salesRep:id,name');

        $accounts = $customer->employeeAccounts()
            ->with('employee.supplier:id,supplier_name,short_name')
            ->when($supplierId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('supplier_id', $supplierId)))
            ->withSum('transactions as debits', 'debit')
            ->withSum('transactions as credits', 'credit')
            ->withMax(['transactions as last_sale' => fn ($q) => $q->where('transaction_type', 'credit_sale')], 'transaction_date')
            ->withMax(['transactions as last_recovery' => fn ($q) => $q->where('transaction_type', 'recovery')], 'transaction_date')
            ->orderBy('account_number')
            ->get();

        return view('reports.shop-list.peek', ['customer' => $customer, 'accounts' => $accounts]);
    }

    /**
     * Filters from the query string; the old parameter names (filter[customer_name], filter[it_status]…) still work.
     *
     * @return array<string, string>
     */
    private function filters(Request $request): array
    {
        $filters = array_filter(array_map(fn ($value) => is_scalar($value) ? trim((string) $value) : '', (array) $request->input('filter', [])), fn (string $value) => $value !== '');

        return Arr::only($filters, [
            'search', 'customer_name', 'customer_code', 'phone', 'email', 'address', 'city', 'sub_locality', 'ntn', 'owner_cnic',
            'channel_type', 'customer_category', 'sales_rep_id', 'is_active', 'it_status', 'supplier_id', 'employee_id', 'balance', 'no_sale', 'missing',
        ]);
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function filteredQuery(array $filters, ?int $supplierId): Builder
    {
        $employeeId = isset($filters['employee_id']) ? (int) $filters['employee_id'] : null;
        $limitedToAccounts = $supplierId || $employeeId;
        $balances = $this->balancesByCustomer($supplierId, $employeeId);

        $query = Customer::query()
            ->with('salesRep:id,name')
            ->{$limitedToAccounts ? 'joinSub' : 'leftJoinSub'}($balances, 'b', 'b.customer_id', '=', 'customers.id')
            ->select('customers.*', 'b.balance', 'b.accounts', 'b.last_sale', 'b.last_recovery');

        foreach (['customer_name', 'customer_code', 'phone', 'email', 'address', 'city', 'sub_locality', 'ntn', 'owner_cnic'] as $column) {
            if (isset($filters[$column])) {
                $query->where('customers.'.$column, 'like', '%'.$filters[$column].'%');
            }
        }

        foreach (['channel_type', 'customer_category', 'sales_rep_id'] as $column) {
            if (isset($filters[$column])) {
                $query->where('customers.'.$column, $filters[$column]);
            }
        }

        if (isset($filters['is_active']) && in_array($filters['is_active'], ['0', '1'], true)) {
            $query->where('customers.is_active', $filters['is_active'] === '1');
        }

        if (isset($filters['it_status']) && in_array($filters['it_status'], ['0', '1'], true)) {
            $query->where('customers.it_status', $filters['it_status'] === '1');
        }

        if (isset($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q->where('customers.customer_name', 'like', $term)
                ->orWhere('customers.customer_code', 'like', $term)
                ->orWhere('customers.business_name', 'like', $term)
                ->orWhere('customers.phone', 'like', $term)
                ->orWhere('customers.address', 'like', $term)
                ->orWhere('customers.sub_locality', 'like', $term)
                ->orWhere('customers.city', 'like', $term)
                ->orWhere('customers.ntn', 'like', $term)
                ->orWhere('customers.owner_cnic', 'like', $term));
        }

        match ($filters['balance'] ?? null) {
            'owes' => $query->where('b.balance', '>', 0.004),
            'advance' => $query->where('b.balance', '<', -0.004),
            'clear' => $query->whereBetween('b.balance', [-0.004, 0.004]),
            'none' => $query->whereNull('b.customer_id'),
            default => null,
        };

        if (isset($filters['no_sale'])) {
            $filters['no_sale'] === 'never'
                ? $query->whereNull('b.last_sale')
                : $query->where(fn ($q) => $q->whereNull('b.last_sale')->orWhere('b.last_sale', '<', now()->subDays((int) $filters['no_sale'])->toDateString()));
        }

        if (isset($filters['missing']) && in_array($filters['missing'], ['phone', 'owner_cnic', 'ntn', 'address'], true)) {
            $column = 'customers.'.$filters['missing'];
            $query->where(fn ($q) => $q->whereNull($column)->orWhere($column, ''));
        }

        return $query;
    }

    /**
     * Balance, salesman accounts, last sale and last recovery per customer, from the salesman accounts.
     */
    private function balancesByCustomer(?int $supplierId, ?int $employeeId): QueryBuilder
    {
        return DB::table('customer_employee_account_transactions as t')
            ->join('customer_employee_accounts as a', 'a.id', '=', 't.customer_employee_account_id')
            ->join('employees as e', 'e.id', '=', 'a.employee_id')
            ->whereNull('t.deleted_at')
            ->when($supplierId, fn ($q) => $q->where('e.supplier_id', $supplierId))
            ->when($employeeId, fn ($q) => $q->where('a.employee_id', $employeeId))
            ->groupBy('a.customer_id')
            ->selectRaw("a.customer_id,
                COALESCE(SUM(t.debit), 0) - COALESCE(SUM(t.credit), 0) as balance,
                COUNT(DISTINCT a.id) as accounts,
                MAX(CASE WHEN t.transaction_type = 'credit_sale' THEN t.transaction_date END) as last_sale,
                MAX(CASE WHEN t.transaction_type = 'recovery' THEN t.transaction_date END) as last_recovery");
    }

    /**
     * Shop counts for the All / Active / Inactive tabs under the other filters.
     *
     * @param  array<string, string>  $filters
     * @return array{total: int, active: int, inactive: int}
     */
    private function stats(array $filters, ?int $supplierId): array
    {
        $byStatus = $this->filteredQuery($filters, $supplierId)->reorder()->toBase()
            ->select(DB::raw('customers.is_active, COUNT(*) as aggregate'))
            ->groupBy('customers.is_active')
            ->pluck('aggregate', 'is_active');

        return [
            'total' => (int) $byStatus->sum(),
            'active' => (int) ($byStatus[1] ?? $byStatus['1'] ?? $byStatus[true] ?? 0),
            'inactive' => (int) ($byStatus[0] ?? $byStatus['0'] ?? $byStatus[false] ?? 0),
        ];
    }

    private function applySort(Builder $query, string $sort): void
    {
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        match (ltrim($sort, '-')) {
            'customer_name' => $query->orderBy('customers.customer_name', $direction),
            'area' => $query->orderBy('customers.city', $direction)->orderBy('customers.sub_locality', $direction),
            'balance' => $query->orderByRaw('b.balance IS NULL')->orderBy('b.balance', $direction),
            'last_sale' => $query->orderByRaw('b.last_sale IS NULL')->orderBy('b.last_sale', $direction),
            default => $query->orderBy('customers.customer_code', $direction),
        };

        $query->orderBy('customers.id');
    }

    /**
     * The supplier whose balances the user sees: their own company, or the one picked in the filters for an admin.
     *
     * @return array{0: int|null, 1: bool}
     */
    private function supplierScope(Request $request): array
    {
        $user = $request->user();
        $canViewAllSuppliers = $user->is_super_admin === 'Yes' || $user->hasRole(['super-admin', 'admin']) || ! $user->supplier_id;
        $requested = $request->integer('filter.supplier_id') ?: null;

        if ($requested && ! $canViewAllSuppliers && $requested !== (int) $user->supplier_id) {
            abort(403, 'You do not have permission to see another company.');
        }

        return [$canViewAllSuppliers ? $requested : (int) $user->supplier_id, $canViewAllSuppliers];
    }

    private function perPage(Request $request): int|string
    {
        $perPage = (string) $request->input('per_page', self::DEFAULT_PER_PAGE);

        if ($perPage === 'all') {
            return 'all';
        }

        return in_array((int) $perPage, self::PER_PAGE_OPTIONS, true) ? (int) $perPage : self::DEFAULT_PER_PAGE;
    }

    /**
     * Shown columns: columns=area,phone,… from the column picker, or the defaults.
     *
     * @return list<string>
     */
    private function columns(Request $request): array
    {
        $requested = array_values(array_intersect(explode(',', (string) $request->query('columns', '')), array_keys(self::COLUMNS)));

        return $requested !== [] ? $requested : array_keys(array_filter(self::COLUMNS, fn (array $column) => $column[1]));
    }

    /**
     * One line naming the filters in use, for the PDF and the print header.
     *
     * @param  array<string, string>  $filters
     */
    private function filterSummary(array $filters, ?int $supplierId): string
    {
        $labels = [
            'search' => 'Search', 'customer_name' => 'Name', 'customer_code' => 'Code', 'phone' => 'Phone', 'email' => 'Email', 'address' => 'Address',
            'city' => 'City', 'sub_locality' => 'Area', 'ntn' => 'NTN', 'owner_cnic' => 'CNIC', 'channel_type' => 'Channel', 'customer_category' => 'Category',
        ];
        $parts = collect(Arr::only($filters, array_keys($labels)))->map(fn ($value, $key) => $labels[$key].': '.($key === 'customer_category' ? 'Category '.$value : $value));

        $parts = $parts->merge(array_filter([
            'supplier' => $supplierId ? 'Supplier: '.Supplier::query()->whereKey($supplierId)->value('supplier_name') : null,
            'employee' => isset($filters['employee_id']) ? 'Salesman: '.Employee::query()->whereKey($filters['employee_id'])->value('name') : null,
            'rep' => isset($filters['sales_rep_id']) ? 'Sales rep: '.User::query()->whereKey($filters['sales_rep_id'])->value('name') : null,
            'status' => isset($filters['is_active']) ? ($filters['is_active'] === '1' ? 'Active' : 'Inactive') : null,
            'filer' => isset($filters['it_status']) ? ($filters['it_status'] === '1' ? 'Filers' : 'Non-filers') : null,
            'balance' => isset($filters['balance']) ? ['owes' => 'Owes money', 'advance' => 'Paid in advance', 'clear' => 'Settled', 'none' => 'No credit account'][$filters['balance']] ?? null : null,
            'no_sale' => isset($filters['no_sale']) ? ($filters['no_sale'] === 'never' ? 'Never sold on credit' : 'No sale in '.(int) $filters['no_sale'].' days') : null,
            'missing' => isset($filters['missing']) ? 'Missing '.Str::of($filters['missing'])->replace('owner_cnic', 'CNIC')->upper()->replace('PHONE', 'phone')->replace('ADDRESS', 'address') : null,
        ]));

        return $parts->values()->implode(' · ') ?: 'All shops';
    }
}
