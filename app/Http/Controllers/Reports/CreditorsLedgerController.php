<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerEmployeeAccountTransaction;
use App\Models\Employee;
use App\Models\Supplier;
use App\Services\LedgerService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CreditorsLedgerController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-audit-creditors-ledger'),
        ];
    }

    public function __construct(protected LedgerService $ledgerService) {}

    /**
     * Display creditors (accounts receivable) ledger summary
     */
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

        $dateFrom = $request->input('filter.date_from');
        $dateTo = $request->input('filter.date_to');

        // Date constraint closure for reuse across queries
        $applyDateFilter = function ($q) use ($dateFrom, $dateTo) {
            if ($dateFrom) {
                $q->where('transaction_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $q->where('transaction_date', '<=', $dateTo);
            }
        };

        // Cross-DB subquery for balance calculation (works on MySQL, MariaDB, PostgreSQL)
        $dateCondition = '';
        $balanceBindings = [];
        if ($dateFrom) {
            $dateCondition .= ' AND ceat_b.transaction_date >= ?';
            $balanceBindings[] = $dateFrom;
        }
        if ($dateTo) {
            $dateCondition .= ' AND ceat_b.transaction_date <= ?';
            $balanceBindings[] = $dateTo;
        }

        // When supplier_id filter is set, scope balance to only that supplier's employee accounts
        $supplierIdFilter = $userSupplierId ?? $requestedSupplierId;
        $supplierJoin = '';
        $supplierCondition = '';
        $supplierBindings = [];
        if ($supplierIdFilter) {
            $supplierJoin = 'JOIN employees e_b ON cea_b.employee_id = e_b.id';
            $supplierCondition = ' AND e_b.supplier_id = ?';
            $supplierBindings[] = $supplierIdFilter;
        } elseif (! $canViewAllSuppliers) {
            $supplierCondition = ' AND 1 = 0';
        }

        $balanceSubquery = "(
            SELECT COALESCE(SUM(ceat_b.debit), 0) - COALESCE(SUM(ceat_b.credit), 0)
            FROM customer_employee_account_transactions ceat_b
            JOIN customer_employee_accounts cea_b ON ceat_b.customer_employee_account_id = cea_b.id
            {$supplierJoin}
            WHERE cea_b.customer_id = customers.id AND ceat_b.deleted_at IS NULL{$dateCondition}{$supplierCondition}
        )";

        // Scope ledger entries by supplier when filter is active
        $applySupplierFilter = function ($q) use ($canViewAllSuppliers, $supplierIdFilter) {
            if ($supplierIdFilter) {
                $q->whereHas('account.employee', fn ($eq) => $eq->where('supplier_id', $supplierIdFilter));
            } elseif (! $canViewAllSuppliers) {
                $q->whereRaw('1 = 0');
            }
        };

        $applyAllFilters = function ($q) use ($applyDateFilter, $applySupplierFilter) {
            $applyDateFilter($q);
            $applySupplierFilter($q);
        };

        // Bindings for balance subquery (date + supplier)
        $balanceBindings = array_merge($balanceBindings, $supplierBindings);

        $customersQuery = Customer::query()
            ->whereHas('ledgerEntries', $applyAllFilters)
            ->withCount(['ledgerEntries' => $applyAllFilters])
            ->withSum(['ledgerEntries as opening_balance' => function ($q) use ($applyAllFilters) {
                $q->where('transaction_type', 'opening_balance');
                $applyAllFilters($q);
            }], 'debit')
            ->withSum(['ledgerEntries as credit_sales' => function ($q) use ($applyAllFilters) {
                $q->where('transaction_type', '!=', 'opening_balance');
                $applyAllFilters($q);
            }], 'debit')
            ->withSum(['ledgerEntries as total_debits' => $applyAllFilters], 'debit')
            ->withSum(['ledgerEntries as total_credits' => $applyAllFilters], 'credit');

        if ($request->filled('filter.customer_name')) {
            $customersQuery->where('customer_name', 'like', '%'.$request->input('filter.customer_name').'%');
        }

        if ($request->filled('filter.customer_code')) {
            $customersQuery->where('customer_code', 'like', '%'.$request->input('filter.customer_code').'%');
        }

        if ($request->filled('filter.business_name')) {
            $customersQuery->where('business_name', 'like', '%'.$request->input('filter.business_name').'%');
        }

        if ($request->filled('filter.phone')) {
            $customersQuery->where('phone', 'like', '%'.$request->input('filter.phone').'%');
        }

        if ($request->filled('filter.city')) {
            $customersQuery->where('city', $request->input('filter.city'));
        }

        if ($request->filled('filter.sub_locality')) {
            $customersQuery->where('sub_locality', 'like', '%'.$request->input('filter.sub_locality').'%');
        }

        if ($request->filled('filter.channel_type')) {
            $customersQuery->where('channel_type', $request->input('filter.channel_type'));
        }

        if ($request->filled('filter.customer_category')) {
            $customersQuery->where('customer_category', $request->input('filter.customer_category'));
        }

        if ($request->filled('filter.is_active')) {
            $customersQuery->where('is_active', $request->input('filter.is_active'));
        }

        if ($request->filled('filter.it_status')) {
            $customersQuery->where('it_status', $request->input('filter.it_status'));
        }

        if ($request->filled('filter.employee_id')) {
            $customersQuery->whereHas('employeeAccounts', function ($q) use ($request) {
                $q->where('employee_id', $request->input('filter.employee_id'));
            });
        }

        if ($supplierIdFilter) {
            $customersQuery->whereHas('employeeAccounts.employee', function ($q) use ($supplierIdFilter) {
                $q->where('supplier_id', $supplierIdFilter);
            });
        } elseif (! $canViewAllSuppliers) {
            $customersQuery->whereRaw('1 = 0');
        }

        if ($request->filled('filter.customer_id')) {
            $customersQuery->where('id', $request->input('filter.customer_id'));
        }

        if ($request->filled('filter.credit_limit_min')) {
            $customersQuery->where('credit_limit', '>=', $request->input('filter.credit_limit_min'));
        }

        if ($request->filled('filter.credit_limit_max')) {
            $customersQuery->where('credit_limit', '<=', $request->input('filter.credit_limit_max'));
        }

        if ($request->filled('filter.has_balance')) {
            if ($request->input('filter.has_balance') === 'yes') {
                $customersQuery->whereRaw("$balanceSubquery > 0", $balanceBindings);
            } elseif ($request->input('filter.has_balance') === 'no') {
                $customersQuery->whereRaw("$balanceSubquery <= 0", $balanceBindings);
            }
        }

        // Cross-DB: use subquery in WHERE instead of havingRaw with aliases
        if ($request->filled('filter.balance_min')) {
            $customersQuery->whereRaw("$balanceSubquery >= ?", [...$balanceBindings, $request->input('filter.balance_min')]);
        }

        if ($request->filled('filter.balance_max')) {
            $customersQuery->whereRaw("$balanceSubquery <= ?", [...$balanceBindings, $request->input('filter.balance_max')]);
        }

        $sort = $request->input('sort', '-balance');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (in_array($column, ['customer_name', 'customer_code', 'city', 'total_debits', 'total_credits', 'opening_balance', 'credit_sales', 'ledger_entries_count'])) {
            $customersQuery->orderBy($column, $direction);
        } elseif ($column === 'balance') {
            // Cross-DB: use subquery in ORDER BY instead of alias
            $customersQuery->orderByRaw("$balanceSubquery $direction", $balanceBindings);
        } else {
            $customersQuery->orderByRaw("$balanceSubquery DESC", $balanceBindings);
        }

        if ($perPage === 'all') {
            $allCustomers = $customersQuery->get();
            $customers = new LengthAwarePaginator(
                $allCustomers,
                $allCustomers->count(),
                $allCustomers->count() ?: 1,
                1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            $customers = $customersQuery->paginate((int) $perPage)->withQueryString();
        }

        // Calculate totals from customer_employee_account_transactions (scoped same as main query)
        $totalsQuery = DB::table('customer_employee_account_transactions as ceat')
            ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
            ->whereNull('ceat.deleted_at');

        if ($dateFrom) {
            $totalsQuery->where('ceat.transaction_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $totalsQuery->where('ceat.transaction_date', '<=', $dateTo);
        }
        if ($supplierIdFilter) {
            $totalsQuery->join('employees as e_t', 'cea.employee_id', '=', 'e_t.id')
                ->where('e_t.supplier_id', $supplierIdFilter);
        } elseif (! $canViewAllSuppliers) {
            $totalsQuery->whereRaw('1 = 0');
        }

        $totals = $totalsQuery
            ->selectRaw('SUM(CASE WHEN ceat.transaction_type = ? THEN ceat.debit ELSE 0 END) as total_opening_balance', ['opening_balance'])
            ->selectRaw('SUM(CASE WHEN ceat.transaction_type != ? THEN ceat.debit ELSE 0 END) as total_credit_sales', ['opening_balance'])
            ->selectRaw('SUM(ceat.debit) as total_debits, SUM(ceat.credit) as total_credits')
            ->first();

        $filterCustomersQuery = Customer::query()
            ->when($supplierIdFilter, fn ($query) => $query->whereHas(
                'employeeAccounts.employee',
                fn ($employeeQuery) => $employeeQuery->where('supplier_id', $supplierIdFilter)
            ))
            ->when(! $canViewAllSuppliers && ! $supplierIdFilter, fn ($query) => $query->whereRaw('1 = 0'));

        $cities = (clone $filterCustomersQuery)->whereNotNull('city')->distinct()->pluck('city')->sort();
        $subLocalities = (clone $filterCustomersQuery)->whereNotNull('sub_locality')->distinct()->pluck('sub_locality')->sort();
        $channelTypes = (clone $filterCustomersQuery)->whereNotNull('channel_type')->distinct()->pluck('channel_type')->sort();
        $employees = Employee::query()
            ->whereHas('customerAccounts')
            ->when($supplierIdFilter, fn ($query) => $query->where('supplier_id', $supplierIdFilter))
            ->when(! $canViewAllSuppliers && ! $supplierIdFilter, fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('name')
            ->get();
        $suppliers = Supplier::query()
            ->whereHas('employees.customerAccounts')
            ->when($supplierIdFilter, fn ($query) => $query->where('id', $supplierIdFilter))
            ->when(! $canViewAllSuppliers && ! $supplierIdFilter, fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('supplier_name')
            ->get(['id', 'supplier_name']);
        $customersList = (clone $filterCustomersQuery)->orderBy('customer_name')->get(['id', 'customer_name', 'customer_code']);

        return view('reports.creditors-ledger.index', [
            'customers' => $customers,
            'totals' => $totals,
            'cities' => $cities,
            'subLocalities' => $subLocalities,
            'channelTypes' => $channelTypes,
            'employees' => $employees,
            'suppliers' => $suppliers,
            'customersList' => $customersList,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'supplierIdFilter' => $supplierIdFilter,
            'canViewAllSuppliers' => $canViewAllSuppliers,
        ]);
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

    /**
     * Display detailed ledger for a specific customer
     */
    public function customerLedger(Request $request, Customer $customer)
    {
        $perPage = $request->input('per_page', 100);
        $perPage = in_array($perPage, [10, 25, 50, 100, 250, 'all']) ? $perPage : 100;

        $dateFrom = $request->input('filter.date_from');
        $dateTo = $request->input('filter.date_to');
        $employeeId = $request->input('filter.employee_id');

        // Helper to apply common filters to a query builder
        $applyFilters = function ($query) use ($request, $dateFrom, $dateTo, $employeeId) {
            if ($employeeId) {
                $query->where('cea.employee_id', $employeeId);
            }
            if ($dateFrom) {
                $query->whereDate('ceat.transaction_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->whereDate('ceat.transaction_date', '<=', $dateTo);
            }
            if ($request->filled('filter.transaction_type')) {
                $query->where('ceat.transaction_type', $request->input('filter.transaction_type'));
            }
            if ($request->filled('filter.reference_number')) {
                $query->where('ceat.reference_number', 'like', '%'.$request->input('filter.reference_number').'%');
            }
            if ($request->filled('filter.description')) {
                $query->where('ceat.description', 'like', '%'.$request->input('filter.description').'%');
            }
            if ($request->filled('filter.invoice_number')) {
                $query->where('ceat.invoice_number', 'like', '%'.$request->input('filter.invoice_number').'%');
            }
            if ($request->filled('filter.payment_method')) {
                $query->where('ceat.payment_method', $request->input('filter.payment_method'));
            }
            if ($request->filled('filter.amount_min')) {
                $query->where(function ($q) use ($request) {
                    $q->where('ceat.debit', '>=', $request->input('filter.amount_min'))
                        ->orWhere('ceat.credit', '>=', $request->input('filter.amount_min'));
                });
            }
            if ($request->filled('filter.amount_max')) {
                $query->where(function ($q) use ($request) {
                    $q->where(function ($inner) use ($request) {
                        $inner->where('ceat.debit', '>', 0)
                            ->where('ceat.debit', '<=', $request->input('filter.amount_max'));
                    })->orWhere(function ($inner) use ($request) {
                        $inner->where('ceat.credit', '>', 0)
                            ->where('ceat.credit', '<=', $request->input('filter.amount_max'));
                    });
                });
            }

            return $query;
        };

        // Base query for entries
        $entriesQuery = DB::table('customer_employee_account_transactions as ceat')
            ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
            ->leftJoin('employees as e', 'cea.employee_id', '=', 'e.id')
            ->leftJoin('sales_settlements as ss', 'ceat.sales_settlement_id', '=', 'ss.id')
            ->where('cea.customer_id', $customer->id)
            ->whereNull('ceat.deleted_at')
            ->select(
                'ceat.*',
                'e.name as employee_name',
                'ss.settlement_number',
                'cea.account_number'
            );

        $applyFilters($entriesQuery);

        $entriesQuery->orderBy('ceat.transaction_date')->orderBy('ceat.id');

        if ($perPage === 'all') {
            // Get all entries without pagination
            $allEntries = $entriesQuery->get();
            $entries = new LengthAwarePaginator(
                $allEntries,
                $allEntries->count(),
                $allEntries->count() ?: 1,
                1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            $entries = $entriesQuery->paginate((int) $perPage)->withQueryString();
        }

        // Calculate opening balance - respects salesman filter
        $openingBalance = 0;
        $openingBalanceQuery = DB::table('customer_employee_account_transactions as ceat')
            ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
            ->where('cea.customer_id', $customer->id)
            ->whereNull('ceat.deleted_at');

        // If salesman is filtered, only get that salesman's account balance
        if ($employeeId) {
            $openingBalanceQuery->where('cea.employee_id', $employeeId);
        }

        if ($dateFrom) {
            $openingBalanceQuery->where('ceat.transaction_date', '<', $dateFrom);
            $openingBalanceResult = $openingBalanceQuery
                ->selectRaw('COALESCE(SUM(ceat.debit), 0) - COALESCE(SUM(ceat.credit), 0) as balance')
                ->first();
            $openingBalance = $openingBalanceResult ? (float) $openingBalanceResult->balance : 0;
        }

        // Calculate balance before current page (for pagination)
        $balanceBeforePage = $openingBalance;
        if ($entries->currentPage() > 1) {
            $beforePageQuery = DB::table('customer_employee_account_transactions as ceat')
                ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
                ->where('cea.customer_id', $customer->id)
                ->whereNull('ceat.deleted_at');

            $applyFilters($beforePageQuery);

            $entriesBeforePage = ($entries->currentPage() - 1) * $entries->perPage();
            $beforePageResult = $beforePageQuery
                ->orderBy('ceat.transaction_date')
                ->orderBy('ceat.id')
                ->limit($entriesBeforePage)
                ->selectRaw('COALESCE(SUM(ceat.debit), 0) - COALESCE(SUM(ceat.credit), 0) as balance')
                ->first();

            $balanceBeforePage = $openingBalance + ($beforePageResult ? (float) $beforePageResult->balance : 0);
        }

        // Calculate running balance for each entry
        $runningBalance = $balanceBeforePage;
        $entries->getCollection()->transform(function ($entry) use (&$runningBalance) {
            $entry->row_opening_balance = $runningBalance;
            $runningBalance += (float) ($entry->debit ?? 0) - (float) ($entry->credit ?? 0);
            $entry->balance = $runningBalance;

            return $entry;
        });

        // Closing balance: opening balance + all filtered transactions (not just current page)
        $closingBalanceQuery = DB::table('customer_employee_account_transactions as ceat')
            ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
            ->where('cea.customer_id', $customer->id)
            ->whereNull('ceat.deleted_at');

        $applyFilters($closingBalanceQuery);

        $filteredTotals = $closingBalanceQuery
            ->selectRaw('COALESCE(SUM(ceat.debit), 0) as total_debit, COALESCE(SUM(ceat.credit), 0) as total_credit')
            ->first();

        $closingBalance = $openingBalance
            + (float) ($filteredTotals->total_debit ?? 0)
            - (float) ($filteredTotals->total_credit ?? 0);

        $summary = [
            'opening_balance' => $openingBalance,
            'total_debits' => (float) ($filteredTotals->total_debit ?? 0),
            'total_credits' => (float) ($filteredTotals->total_credit ?? 0),
            'closing_balance' => $closingBalance,
        ];

        // Get transaction types from the actual transactions
        $transactionTypes = DB::table('customer_employee_account_transactions')
            ->distinct()
            ->whereNull('deleted_at')
            ->pluck('transaction_type');

        // Get payment methods for filter
        $paymentMethods = DB::table('customer_employee_account_transactions')
            ->whereNotNull('payment_method')
            ->where('payment_method', '!=', '')
            ->distinct()
            ->whereNull('deleted_at')
            ->pluck('payment_method')
            ->sort();

        // Get employees for filter dropdown
        $employees = Employee::whereHas('customerAccounts', function ($q) use ($customer) {
            $q->where('customer_id', $customer->id);
        })->orderBy('name')->get();

        return view('reports.creditors-ledger.customer-ledger', [
            'customer' => $customer,
            'entries' => $entries,
            'summary' => $summary,
            'transactionTypes' => $transactionTypes,
            'paymentMethods' => $paymentMethods,
            'employees' => $employees,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }

    /**
     * Salesman-wise creditors: what each salesman's customers still owe, and how much is 60+ days old.
     */
    public function salesmanCreditors(Request $request)
    {
        $supplierIdFilter = $this->resolveSupplierFilter($request);
        $accounts = $this->accountBalances(now()->toDateString(), $supplierIdFilter, null);
        $today = now()->startOfDay();

        $salesmen = $accounts->groupBy('employee_id')->map(function ($rows) use ($today) {
            $owing = $rows->filter(fn ($row) => $row->balance > 0);
            $overdue = $owing->filter(fn ($row) => $this->daysSinceLastPayment($row, $today) > 60);
            $lastRecovery = $rows->pluck('last_recovery')->filter()->max();

            return (object) [
                'employee_id' => $rows->first()->employee_id,
                'salesman' => $rows->first()->salesman,
                'supplier' => $rows->first()->supplier,
                'customers' => $owing->count(),
                'credit_sales' => (float) $rows->sum('credit_sales'),
                'recoveries' => (float) $rows->sum('recoveries'),
                'balance' => (float) $rows->sum('balance'),
                'overdue' => (float) $overdue->sum('balance'),
                'overdue_customers' => $overdue->count(),
                'last_recovery' => $lastRecovery,
            ];
        });

        if ($request->filled('filter.employee_name')) {
            $needle = mb_strtolower($request->input('filter.employee_name'));
            $salesmen = $salesmen->filter(fn ($row) => str_contains(mb_strtolower((string) $row->salesman), $needle));
        }

        $salesmen = $salesmen->sortByDesc('balance')->values();

        return view('reports.creditors-ledger.salesman-creditors', [
            'salesmen' => $salesmen,
            'suppliers' => $this->supplierOptions($supplierIdFilter),
            'supplierIdFilter' => $supplierIdFilter,
            'canViewAllSuppliers' => $this->canViewAllSuppliers(),
        ]);
    }

    /**
     * Display customer's credit sales with salesman breakdown
     */
    public function customerCreditSales(Request $request, Customer $customer)
    {
        $perPage = $request->input('per_page', 50);
        $perPage = in_array($perPage, [10, 25, 50, 100, 250]) ? $perPage : 50;

        // Query credit sales from customer_employee_account_transactions
        $creditSalesQuery = CustomerEmployeeAccountTransaction::query()
            ->select('customer_employee_account_transactions.*', 'cea.employee_id', 'ss.settlement_number', 'ss.settlement_date')
            ->join('customer_employee_accounts as cea', 'customer_employee_account_transactions.customer_employee_account_id', '=', 'cea.id')
            ->leftJoin('sales_settlements as ss', 'customer_employee_account_transactions.sales_settlement_id', '=', 'ss.id')
            ->where('cea.customer_id', $customer->id)
            ->where('customer_employee_account_transactions.transaction_type', 'credit_sale')
            ->with(['account.employee', 'salesSettlement']);

        if ($request->filled('filter.date_from')) {
            $creditSalesQuery->whereDate('customer_employee_account_transactions.transaction_date', '>=', $request->input('filter.date_from'));
        }

        if ($request->filled('filter.date_to')) {
            $creditSalesQuery->whereDate('customer_employee_account_transactions.transaction_date', '<=', $request->input('filter.date_to'));
        }

        $creditSales = $creditSalesQuery->orderByDesc('customer_employee_account_transactions.transaction_date')
            ->paginate($perPage)
            ->withQueryString();

        // Get salesman breakdown
        $salesmenBreakdown = DB::table('customer_employee_account_transactions as ceat')
            ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
            ->join('employees as e', 'cea.employee_id', '=', 'e.id')
            ->where('cea.customer_id', $customer->id)
            ->where('ceat.transaction_type', 'credit_sale')
            ->whereNull('ceat.deleted_at')
            ->select('cea.employee_id', 'e.name as employee_name')
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw('SUM(ceat.debit) as total_amount')
            ->groupBy('cea.employee_id', 'e.name')
            ->orderByDesc('total_amount')
            ->get();

        $currentBalance = $this->ledgerService->getCustomerBalance($customer->id);

        return view('reports.creditors-ledger.customer-credit-sales', [
            'customer' => $customer,
            'creditSales' => $creditSales,
            'salesmenBreakdown' => $salesmenBreakdown,
            'currentBalance' => $currentBalance,
        ]);
    }

    /**
     * Aging report: every customer account (customer + salesman) still owing, aged by days since its last payment.
     */
    public function agingReport(Request $request)
    {
        $asOfDate = $request->input('as_of_date') ?: now()->toDateString();
        $supplierIdFilter = $this->resolveSupplierFilter($request);
        $employeeId = $request->integer('filter.employee_id') ?: null;
        $bucketFilter = $request->input('filter.bucket');
        $asOf = Carbon::parse($asOfDate)->startOfDay();

        $rows = $this->accountBalances($asOfDate, $supplierIdFilter, $employeeId)
            ->filter(fn ($row) => $row->balance > 0)
            ->map(function ($row) use ($asOf) {
                $row->days = $this->daysSinceLastPayment($row, $asOf);
                $row->bucket = match (true) {
                    $row->days <= 30 => 'current',
                    $row->days <= 60 => '31_60',
                    $row->days <= 90 => '61_90',
                    default => 'over_90',
                };

                return $row;
            });

        if ($request->filled('filter.customer')) {
            $needle = mb_strtolower($request->input('filter.customer'));
            $rows = $rows->filter(fn ($row) => str_contains(mb_strtolower($row->customer_name.' '.$row->customer_code), $needle));
        }

        $buckets = ['current' => '0-30 days', '31_60' => '31-60 days', '61_90' => '61-90 days', 'over_90' => 'Over 90 days'];
        $totals = collect($buckets)->map(fn ($label, $key) => [
            'label' => $label,
            'amount' => (float) $rows->where('bucket', $key)->sum('balance'),
            'count' => $rows->where('bucket', $key)->count(),
        ]);

        if ($bucketFilter === '60_plus') {
            $rows = $rows->whereIn('bucket', ['61_90', 'over_90']);
        } elseif (isset($buckets[$bucketFilter])) {
            $rows = $rows->where('bucket', $bucketFilter);
        }

        $rows = $rows->sortByDesc('balance')->values();

        $perPage = $request->input('per_page', 100);
        $perPage = in_array($perPage, [50, 100, 250, 'all']) ? $perPage : 100;
        $size = $perPage === 'all' ? max($rows->count(), 1) : (int) $perPage;
        $page = $perPage === 'all' ? 1 : LengthAwarePaginator::resolveCurrentPage();
        $accounts = new LengthAwarePaginator(
            $rows->forPage($page, $size)->values(),
            $rows->count(),
            $size,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('reports.creditors-ledger.aging-report', [
            'accounts' => $accounts,
            'filteredTotal' => (float) $rows->sum('balance'),
            'totals' => $totals,
            'buckets' => $buckets,
            'asOfDate' => $asOfDate,
            'suppliers' => $this->supplierOptions($supplierIdFilter),
            'employees' => $this->employeeOptions($supplierIdFilter),
            'supplierIdFilter' => $supplierIdFilter,
            'canViewAllSuppliers' => $this->canViewAllSuppliers(),
        ]);
    }

    /**
     * Supplier to report on: a supplier user is locked to their own supplier; admins may pick one or see all.
     */
    private function resolveSupplierFilter(Request $request): ?int
    {
        $requested = $request->integer('filter.supplier_id') ?: null;
        $userSupplierId = $this->getUserSupplierScope();

        if ($requested && ! $this->canViewAllSuppliers() && $requested !== $userSupplierId) {
            abort(403, 'You do not have permission to filter by this supplier.');
        }

        return $userSupplierId ?? $requested;
    }

    /**
     * One row per customer account (customer + salesman) with its balance up to the given date.
     *
     * @return Collection<int, object>
     */
    private function accountBalances(string $asOfDate, ?int $supplierId, ?int $employeeId): Collection
    {
        $balance = 'COALESCE(SUM(ceat.debit), 0) - COALESCE(SUM(ceat.credit), 0)';

        return DB::table('customer_employee_account_transactions as ceat')
            ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
            ->join('customers as c', 'cea.customer_id', '=', 'c.id')
            ->join('employees as e', 'cea.employee_id', '=', 'e.id')
            ->leftJoin('suppliers as s', 'e.supplier_id', '=', 's.id')
            ->whereNull('ceat.deleted_at')
            ->whereNull('cea.deleted_at')
            ->whereDate('ceat.transaction_date', '<=', $asOfDate)
            ->when($supplierId, fn ($q) => $q->where('e.supplier_id', $supplierId))
            ->when(! $supplierId && ! $this->canViewAllSuppliers(), fn ($q) => $q->whereRaw('1 = 0'))
            ->when($employeeId, fn ($q) => $q->where('cea.employee_id', $employeeId))
            ->select('cea.id as account_id', 'cea.customer_id', 'c.customer_code', 'c.customer_name', 'c.city', 'cea.employee_id', 'e.name as salesman', 'e.supplier_id', 's.supplier_name as supplier')
            ->selectRaw("{$balance} as balance")
            ->selectRaw("COALESCE(SUM(CASE WHEN ceat.transaction_type <> 'opening_balance' THEN ceat.debit ELSE 0 END), 0) as credit_sales")
            ->selectRaw('COALESCE(SUM(ceat.credit), 0) as recoveries')
            ->selectRaw('MAX(CASE WHEN ceat.credit > 0 THEN ceat.transaction_date END) as last_recovery')
            ->selectRaw('MAX(CASE WHEN ceat.debit > 0 THEN ceat.transaction_date END) as last_credit_sale')
            ->groupBy('cea.id', 'cea.customer_id', 'c.customer_code', 'c.customer_name', 'c.city', 'cea.employee_id', 'e.name', 'e.supplier_id', 's.supplier_name')
            ->get()
            ->map(function ($row) {
                $row->balance = round((float) $row->balance, 2);
                $row->credit_sales = (float) $row->credit_sales;
                $row->recoveries = (float) $row->recoveries;

                return $row;
            });
    }

    /**
     * Days since the account's last payment, or since its credit sale when it never paid.
     */
    private function daysSinceLastPayment(object $row, Carbon $asOf): int
    {
        $since = $row->last_recovery ?? $row->last_credit_sale;

        return $since ? (int) abs(Carbon::parse($since)->startOfDay()->diffInDays($asOf)) : 9999;
    }

    /**
     * @return Collection<int, Supplier>
     */
    private function supplierOptions(?int $supplierIdFilter): Collection
    {
        return Supplier::query()
            ->whereHas('employees.customerAccounts')
            ->when(! $this->canViewAllSuppliers(), fn ($q) => $supplierIdFilter ? $q->where('id', $supplierIdFilter) : $q->whereRaw('1 = 0'))
            ->orderBy('supplier_name')
            ->get(['id', 'supplier_name']);
    }

    /**
     * @return Collection<int, Employee>
     */
    private function employeeOptions(?int $supplierIdFilter): Collection
    {
        return Employee::query()
            ->whereHas('customerAccounts')
            ->when($supplierIdFilter, fn ($q) => $q->where('supplier_id', $supplierIdFilter))
            ->when(! $supplierIdFilter && ! $this->canViewAllSuppliers(), fn ($q) => $q->whereRaw('1 = 0'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
