<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerEmployeeAccountTransaction;
use App\Models\Employee;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
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
        $dateBindings = [];
        if ($dateFrom) {
            $dateCondition .= ' AND ceat_b.transaction_date >= ?';
            $dateBindings[] = $dateFrom;
        }
        if ($dateTo) {
            $dateCondition .= ' AND ceat_b.transaction_date <= ?';
            $dateBindings[] = $dateTo;
        }

        $balanceSubquery = "(
            SELECT COALESCE(SUM(ceat_b.debit), 0) - COALESCE(SUM(ceat_b.credit), 0)
            FROM customer_employee_account_transactions ceat_b
            JOIN customer_employee_accounts cea_b ON ceat_b.customer_employee_account_id = cea_b.id
            WHERE cea_b.customer_id = customers.id AND ceat_b.deleted_at IS NULL{$dateCondition}
        )";

        // Add bindings for the balance subquery used in whereRaw/orderByRaw
        $customersQuery = Customer::query()
            ->whereHas('ledgerEntries', $applyDateFilter)
            ->withCount(['ledgerEntries' => $applyDateFilter])
            ->withSum(['ledgerEntries as opening_balance' => function ($q) use ($applyDateFilter) {
                $q->where('transaction_type', 'opening_balance');
                $applyDateFilter($q);
            }], 'debit')
            ->withSum(['ledgerEntries as credit_sales' => function ($q) use ($applyDateFilter) {
                $q->where('transaction_type', '!=', 'opening_balance');
                $applyDateFilter($q);
            }], 'debit')
            ->withSum(['ledgerEntries as total_debits' => $applyDateFilter], 'debit')
            ->withSum(['ledgerEntries as total_credits' => $applyDateFilter], 'credit');

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

        if ($request->filled('filter.credit_limit_min')) {
            $customersQuery->where('credit_limit', '>=', $request->input('filter.credit_limit_min'));
        }

        if ($request->filled('filter.credit_limit_max')) {
            $customersQuery->where('credit_limit', '<=', $request->input('filter.credit_limit_max'));
        }

        if ($request->filled('filter.has_balance')) {
            if ($request->input('filter.has_balance') === 'yes') {
                $customersQuery->whereRaw("$balanceSubquery > 0", $dateBindings);
            } elseif ($request->input('filter.has_balance') === 'no') {
                $customersQuery->whereRaw("$balanceSubquery <= 0", $dateBindings);
            }
        }

        // Cross-DB: use subquery in WHERE instead of havingRaw with aliases
        if ($request->filled('filter.balance_min')) {
            $customersQuery->whereRaw("$balanceSubquery >= ?", [...$dateBindings, $request->input('filter.balance_min')]);
        }

        if ($request->filled('filter.balance_max')) {
            $customersQuery->whereRaw("$balanceSubquery <= ?", [...$dateBindings, $request->input('filter.balance_max')]);
        }

        $sort = $request->input('sort', '-balance');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (in_array($column, ['customer_name', 'customer_code', 'city', 'total_debits', 'total_credits', 'opening_balance', 'credit_sales', 'ledger_entries_count'])) {
            $customersQuery->orderBy($column, $direction);
        } elseif ($column === 'balance') {
            // Cross-DB: use subquery in ORDER BY instead of alias
            $customersQuery->orderByRaw("$balanceSubquery $direction", $dateBindings);
        } else {
            $customersQuery->orderByRaw("$balanceSubquery DESC", $dateBindings);
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

        // Calculate totals from customer_employee_account_transactions
        $totalsQuery = DB::table('customer_employee_account_transactions')
            ->whereNull('deleted_at');

        if ($dateFrom) {
            $totalsQuery->where('transaction_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $totalsQuery->where('transaction_date', '<=', $dateTo);
        }

        $totals = $totalsQuery
            ->selectRaw('SUM(CASE WHEN transaction_type = ? THEN debit ELSE 0 END) as total_opening_balance', ['opening_balance'])
            ->selectRaw('SUM(CASE WHEN transaction_type != ? THEN debit ELSE 0 END) as total_credit_sales', ['opening_balance'])
            ->selectRaw('SUM(debit) as total_debits, SUM(credit) as total_credits')
            ->first();

        $cities = Customer::whereNotNull('city')->distinct()->pluck('city')->sort();
        $subLocalities = Customer::whereNotNull('sub_locality')->distinct()->pluck('sub_locality')->sort();
        $channelTypes = Customer::whereNotNull('channel_type')->distinct()->pluck('channel_type')->sort();
        $employees = Employee::whereHas('customerAccounts')->orderBy('name')->get();

        return view('reports.creditors-ledger.index', [
            'customers' => $customers,
            'totals' => $totals,
            'cities' => $cities,
            'subLocalities' => $subLocalities,
            'channelTypes' => $channelTypes,
            'employees' => $employees,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
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

        // Closing balance: respects salesman filter
        if ($employeeId) {
            $closingBalance = $this->ledgerService->getCustomerBalanceByEmployee($customer->id, (int) $employeeId);
        } else {
            $closingBalance = $this->ledgerService->getCustomerBalance($customer->id);
        }

        $summary = [
            'opening_balance' => $openingBalance,
            'total_debits' => $entries->sum('debit'),
            'total_credits' => $entries->sum('credit'),
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
     * Display salesman-wise creditors ledger
     */
    public function salesmanCreditors(Request $request)
    {
        $perPage = $request->input('per_page', 50);
        $perPage = in_array($perPage, [10, 25, 50, 100, 250]) ? $perPage : 50;

        $salesmenQuery = Employee::query()
            ->whereHas('creditSales')
            ->with('supplier')
            ->withCount('creditSales')
            ->withSum('creditSales', 'sale_amount');

        if ($request->filled('filter.employee_name')) {
            $salesmenQuery->where(function ($q) use ($request) {
                $q->where('first_name', 'like', '%'.$request->input('filter.employee_name').'%')
                    ->orWhere('last_name', 'like', '%'.$request->input('filter.employee_name').'%');
            });
        }

        $salesmen = $salesmenQuery->orderByDesc('credit_sales_sum_sale_amount')
            ->paginate($perPage)
            ->withQueryString();

        return view('reports.creditors-ledger.salesman-creditors', [
            'salesmen' => $salesmen,
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
     * Aging report for accounts receivable
     */
    public function agingReport(Request $request)
    {
        $asOfDate = $request->input('as_of_date', now()->toDateString());

        $customers = Customer::whereHas('ledgerEntries')
            ->with([
                'ledgerEntries' => function ($q) use ($asOfDate) {
                    $q->whereDate('transaction_date', '<=', $asOfDate)
                        ->orderBy('transaction_date', 'desc');
                },
            ])
            ->get()
            ->map(function ($customer) {
                $balance = $customer->ledgerEntries->first()?->balance ?? 0;

                if ($balance <= 0) {
                    return null;
                }

                $lastDebitEntry = $customer->ledgerEntries->where('debit', '>', 0)->first();
                $daysSinceLastDebit = $lastDebitEntry
                    ? now()->diffInDays($lastDebitEntry->transaction_date)
                    : 0;

                return [
                    'customer' => $customer,
                    'balance' => $balance,
                    'days_outstanding' => $daysSinceLastDebit,
                    'current' => $daysSinceLastDebit <= 30 ? $balance : 0,
                    '31_60_days' => $daysSinceLastDebit > 30 && $daysSinceLastDebit <= 60 ? $balance : 0,
                    '61_90_days' => $daysSinceLastDebit > 60 && $daysSinceLastDebit <= 90 ? $balance : 0,
                    'over_90_days' => $daysSinceLastDebit > 90 ? $balance : 0,
                ];
            })
            ->filter();

        return view('reports.creditors-ledger.aging-report', [
            'customers' => $customers,
            'asOfDate' => $asOfDate,
        ]);
    }
}
