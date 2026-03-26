<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class CreditSalesReportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-sales-credit-sales'),
        ];
    }

    public function salesmanCreditHistory(Request $request)
    {
        // Default date range: current month
        if (! $request->has('filter.start_date')) {
            $request->merge([
                'filter' => array_merge($request->input('filter', []), [
                    'start_date' => now()->startOfMonth()->format('Y-m-d'),
                    'end_date' => now()->format('Y-m-d'),
                ]),
            ]);
        }

        $startDate = $request->input('filter.start_date');
        $endDate = $request->input('filter.end_date');
        $employeeIds = $request->input('filter.employee_ids', []);
        if (! is_array($employeeIds)) {
            $employeeIds = array_filter([$employeeIds]);
        }
        $employeeIds = array_filter($employeeIds);

        $perPage = $request->input('per_page', 50);
        $perPage = in_array($perPage, [25, 50, 100, 250]) ? $perPage : 50;

        $query = Employee::query()
            ->select('employees.*')
            // Opening Balance: sum of (debit - credit) for all transactions BEFORE start_date,
            // PLUS any 'opening_balance' type transactions within the period
            ->selectSub(function ($query) use ($startDate, $endDate) {
                $query->from('customer_employee_account_transactions as ceat')
                    ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
                    ->whereColumn('cea.employee_id', 'employees.id')
                    ->where(function ($q) use ($startDate, $endDate) {
                        $q->where('ceat.transaction_date', '<', $startDate)
                            ->orWhere(function ($q2) use ($startDate, $endDate) {
                                $q2->where('ceat.transaction_type', 'opening_balance')
                                    ->whereBetween('ceat.transaction_date', [$startDate, $endDate]);
                            });
                    })
                    ->whereNull('ceat.deleted_at')
                    ->selectRaw('COALESCE(SUM(ceat.debit), 0) - COALESCE(SUM(ceat.credit), 0)');
            }, 'opening_balance')
            // Credit Sales in period
            ->selectSub(function ($query) use ($startDate, $endDate) {
                $query->from('customer_employee_account_transactions as ceat')
                    ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
                    ->whereColumn('cea.employee_id', 'employees.id')
                    ->where('ceat.transaction_type', 'credit_sale')
                    ->whereBetween('ceat.transaction_date', [$startDate, $endDate])
                    ->whereNull('ceat.deleted_at')
                    ->selectRaw('COALESCE(SUM(ceat.debit), 0)');
            }, 'credit_sales_amount')
            // Recoveries in period
            ->selectSub(function ($query) use ($startDate, $endDate) {
                $query->from('customer_employee_account_transactions as ceat')
                    ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
                    ->whereColumn('cea.employee_id', 'employees.id')
                    ->where('ceat.transaction_type', 'recovery')
                    ->whereBetween('ceat.transaction_date', [$startDate, $endDate])
                    ->whereNull('ceat.deleted_at')
                    ->selectRaw('COALESCE(SUM(ceat.credit), 0)');
            }, 'recoveries_amount')
            // Credit Sales Count in period
            ->selectSub(function ($query) use ($startDate, $endDate) {
                $query->from('customer_employee_account_transactions as ceat')
                    ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
                    ->whereColumn('cea.employee_id', 'employees.id')
                    ->where('ceat.transaction_type', 'credit_sale')
                    ->whereBetween('ceat.transaction_date', [$startDate, $endDate])
                    ->whereNull('ceat.deleted_at')
                    ->selectRaw('COUNT(*)');
            }, 'credit_sales_count')
            // Unique Customers count in period
            ->selectSub(function ($query) use ($startDate, $endDate) {
                $query->from('customer_employee_account_transactions as ceat')
                    ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
                    ->whereColumn('cea.employee_id', 'employees.id')
                    ->where('ceat.transaction_type', 'credit_sale')
                    ->whereBetween('ceat.transaction_date', [$startDate, $endDate])
                    ->whereNull('ceat.deleted_at')
                    ->selectRaw('COUNT(DISTINCT cea.customer_id)');
            }, 'customers_count');

        // Filter by supplier
        if ($request->filled('filter.supplier_id')) {
            $query->where('supplier_id', $request->input('filter.supplier_id'));
        }

        // Filter by designation
        if ($request->filled('filter.designation')) {
            $query->where('designation', $request->input('filter.designation'));
        }

        // Filter by selected employees
        if (! empty($employeeIds)) {
            $query->whereIn('employees.id', $employeeIds);
        }

        // Filter by name
        if ($request->filled('filter.name')) {
            $query->where('name', 'like', '%'.$request->input('filter.name').'%');
        }

        // Filter by employee code
        if ($request->filled('filter.employee_code')) {
            $query->where('employee_code', 'like', '%'.$request->input('filter.employee_code').'%');
        }

        // Show all salesmen who have ANY transactions (opening_balance, credit_sale, recovery, etc.)
        // so page totals match grand totals in summary cards
        if (
            ! $request->filled('filter.supplier_id') &&
            ! $request->filled('filter.designation') &&
            empty($employeeIds) &&
            ! $request->filled('filter.name') &&
            ! $request->filled('filter.employee_code')
        ) {
            $query->whereExists(function ($query) {
                $query->from('customer_employee_account_transactions as ceat')
                    ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
                    ->whereColumn('cea.employee_id', 'employees.id')
                    ->whereNull('ceat.deleted_at');
            });
        }

        // Sorting
        $sort = $request->input('sort', '-closing_balance');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        switch ($column) {
            case 'credit_sales':
                $query->orderByRaw('credit_sales_amount '.$direction);
                break;
            case 'recoveries':
                $query->orderByRaw('recoveries_amount '.$direction);
                break;
            case 'opening_balance':
                $query->orderByRaw('opening_balance '.$direction);
                break;
            case 'closing_balance':
                $query->orderByRaw('(opening_balance + credit_sales_amount - recoveries_amount) '.$direction);
                break;
            case 'name':
                $query->orderBy('name', $direction);
                break;
            case 'customers_count':
                $query->orderByRaw('customers_count '.$direction);
                break;
            case 'sales_count':
                $query->orderByRaw('credit_sales_count '.$direction);
                break;
            default:
                $query->orderByRaw('(opening_balance + credit_sales_amount - recoveries_amount) desc');
        }

        $salesmenWithCredits = $query->paginate($perPage)->withQueryString();

        // Calculate totals for period
        // Note: Totals calculation currently sums EVERYTHING in the DB. This might need to be filtered by the same criteria if we want "Page Totals" vs "Grand Totals".
        // For now, keeping it as "Grand Total of all transactions" or adapting to filter?
        // The original logic seemed to sum everything. Let's keep it consistent unless requested.

        $totals = DB::table('customer_employee_account_transactions as ceat')
            ->whereNull('ceat.deleted_at')
            ->selectRaw('SUM(CASE WHEN ceat.transaction_date < ? OR (ceat.transaction_type = \'opening_balance\' AND ceat.transaction_date BETWEEN ? AND ?) THEN (ceat.debit - ceat.credit) ELSE 0 END) as total_opening_balance', [$startDate, $startDate, $endDate])
            ->selectRaw("SUM(CASE WHEN ceat.transaction_type = 'credit_sale' AND ceat.transaction_date BETWEEN ? AND ? THEN ceat.debit ELSE 0 END) as total_credit_sales", [$startDate, $endDate])
            ->selectRaw("SUM(CASE WHEN ceat.transaction_type = 'recovery' AND ceat.transaction_date BETWEEN ? AND ? THEN ceat.credit ELSE 0 END) as total_recoveries", [$startDate, $endDate])
            ->first();

        // Get all active employees for multi-select filter
        $employeesQuery = Employee::where('is_active', true)
            ->orderBy('name');

        if ($request->filled('filter.supplier_id')) {
            $employeesQuery->where('supplier_id', $request->input('filter.supplier_id'));
        }

        if ($request->filled('filter.designation')) {
            $employeesQuery->where('designation', $request->input('filter.designation'));
        }

        $employees = $employeesQuery->get(['id', 'name', 'employee_code']);

        // Get all suppliers for filter
        $suppliers = Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']);

        // Get unique designations
        $designations = Employee::distinct()->whereNotNull('designation')->orderBy('designation')->pluck('designation');

        // Get selected employee names for display
        $selectedEmployeeNames = ! empty($employeeIds)
            ? $employees->whereIn('id', $employeeIds)->pluck('name')->implode(', ')
            : 'All Salesmen';

        $selectedSupplierName = $request->filled('filter.supplier_id')
            ? $suppliers->firstWhere('id', $request->input('filter.supplier_id'))?->supplier_name
            : null;

        return view('reports.credit-sales.salesman-history', [
            'salesmen' => $salesmenWithCredits,
            'totals' => $totals,
            'employees' => $employees,
            'suppliers' => $suppliers,
            'designations' => $designations, // Pass designations
            'selectedEmployeeIds' => $employeeIds,
            'selectedEmployeeNames' => $selectedEmployeeNames,
            'selectedSupplierId' => $request->input('filter.supplier_id'),
            'selectedDesignation' => $request->input('filter.designation'), // Pass selected designation
            'selectedSupplierName' => $selectedSupplierName,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    public function salesmanCreditDetails(Request $request, Employee $employee)
    {
        $perPage = $request->input('per_page', 100);
        $showAll = $perPage === 'all';
        if (! $showAll) {
            $perPage = in_array((int) $perPage, [25, 50, 100, 250]) ? (int) $perPage : 100;
        }

        $dateFrom = $request->input('filter.date_from');
        $dateTo = $request->input('filter.date_to');
        $customerId = $request->input('filter.customer_id');

        // Helper to apply common filters
        $applyFilters = function ($query) use ($request, $dateFrom, $dateTo, $customerId) {
            if ($customerId) {
                $query->where('cea.customer_id', $customerId);
            }
            if ($dateFrom) {
                $query->where('ceat.transaction_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->where('ceat.transaction_date', '<=', $dateTo);
            }
            if ($request->filled('filter.transaction_type')) {
                $query->where('ceat.transaction_type', $request->input('filter.transaction_type'));
            }
            if ($request->filled('filter.invoice_number')) {
                $query->where('ceat.invoice_number', 'like', '%'.$request->input('filter.invoice_number').'%');
            }
            if ($request->filled('filter.description')) {
                $query->where('ceat.description', 'like', '%'.$request->input('filter.description').'%');
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

        // All transaction types for this salesman (not just credit_sale)
        $entriesQuery = DB::table('customer_employee_account_transactions as ceat')
            ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
            ->leftJoin('customers as c', 'cea.customer_id', '=', 'c.id')
            ->leftJoin('sales_settlements as ss', 'ceat.sales_settlement_id', '=', 'ss.id')
            ->where('cea.employee_id', $employee->id)
            ->whereNull('ceat.deleted_at')
            ->select(
                'ceat.*',
                'c.customer_name',
                'c.customer_code',
                'cea.customer_id',
                'ss.settlement_number'
            );

        $applyFilters($entriesQuery);

        $entriesQuery->orderBy('ceat.transaction_date')->orderBy('ceat.id');

        if ($showAll) {
            $allResults = $entriesQuery->get();
            $entries = new LengthAwarePaginator(
                $allResults,
                $allResults->count(),
                $allResults->count() ?: 1,
                1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            $entries = $entriesQuery->paginate($perPage)->withQueryString();
        }

        // Calculate opening balance (transactions before date_from)
        $openingBalance = 0;
        if ($dateFrom) {
            $openingBalanceQuery = DB::table('customer_employee_account_transactions as ceat')
                ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
                ->where('cea.employee_id', $employee->id)
                ->whereNull('ceat.deleted_at')
                ->where('ceat.transaction_date', '<', $dateFrom);

            if ($customerId) {
                $openingBalanceQuery->where('cea.customer_id', $customerId);
            }

            $openingBalanceResult = $openingBalanceQuery
                ->selectRaw('COALESCE(SUM(ceat.debit), 0) - COALESCE(SUM(ceat.credit), 0) as balance')
                ->first();
            $openingBalance = $openingBalanceResult ? (float) $openingBalanceResult->balance : 0;
        }

        // Calculate balance before current page (for pagination running balance)
        $balanceBeforePage = $openingBalance;
        if ($entries->currentPage() > 1) {
            $beforePageQuery = DB::table('customer_employee_account_transactions as ceat')
                ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
                ->where('cea.employee_id', $employee->id)
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

        // Customer summaries with opening/closing balance per customer — respect date filter
        // Opening balance subquery per customer (transactions before date_from for this salesman)
        $custOpeningBalSql = $dateFrom
            ? "(SELECT COALESCE(SUM(ob.debit), 0) - COALESCE(SUM(ob.credit), 0)
                FROM customer_employee_account_transactions ob
                JOIN customer_employee_accounts ob_cea ON ob.customer_employee_account_id = ob_cea.id
                WHERE ob_cea.customer_id = cea.customer_id
                AND ob_cea.employee_id = ?
                AND ob.deleted_at IS NULL
                AND ob.transaction_date < ?)"
            : '0';
        $custOpeningBindings = $dateFrom ? [$employee->id, $dateFrom] : [];

        $customerSummariesQuery = DB::table('customer_employee_account_transactions as ceat')
            ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
            ->join('customers as c', 'cea.customer_id', '=', 'c.id')
            ->where('cea.employee_id', $employee->id)
            ->whereNull('ceat.deleted_at');

        if ($dateFrom) {
            $customerSummariesQuery->where('ceat.transaction_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $customerSummariesQuery->where('ceat.transaction_date', '<=', $dateTo);
        }

        $customerSummaries = $customerSummariesQuery
            ->select(
                'c.id as customer_id',
                'c.customer_name',
                'c.customer_code'
            )
            ->selectRaw("$custOpeningBalSql as opening_balance", $custOpeningBindings)
            ->selectRaw('COUNT(*) as txn_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN ceat.transaction_type = 'credit_sale' THEN ceat.debit ELSE 0 END), 0) as credit_sales")
            ->selectRaw("COALESCE(SUM(CASE WHEN ceat.transaction_type = 'recovery' THEN ceat.credit ELSE 0 END), 0) as recoveries")
            ->selectRaw('COALESCE(SUM(ceat.debit), 0) as total_debits')
            ->selectRaw('COALESCE(SUM(ceat.credit), 0) as total_credits')
            ->groupBy('c.id', 'c.customer_name', 'c.customer_code')
            ->orderByRaw('(COALESCE(SUM(ceat.debit), 0) - COALESCE(SUM(ceat.credit), 0)) DESC')
            ->get();

        // Get customers for filter dropdown
        $customers = Customer::whereHas('employeeAccounts', function ($q) use ($employee) {
            $q->where('employee_id', $employee->id);
        })->orderBy('customer_name')->get();

        // Grand totals — respect date filter
        $totalsQuery = DB::table('customer_employee_account_transactions as ceat')
            ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
            ->where('cea.employee_id', $employee->id)
            ->whereNull('ceat.deleted_at');

        if ($dateFrom) {
            $totalsQuery->where('ceat.transaction_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $totalsQuery->where('ceat.transaction_date', '<=', $dateTo);
        }

        if ($customerId) {
            $totalsQuery->where('cea.customer_id', $customerId);
        }

        $totalsData = $totalsQuery
            ->selectRaw("COALESCE(SUM(CASE WHEN ceat.transaction_type = 'credit_sale' THEN ceat.debit ELSE 0 END), 0) as total_credit_sales")
            ->selectRaw("COALESCE(SUM(CASE WHEN ceat.transaction_type = 'recovery' THEN ceat.credit ELSE 0 END), 0) as total_recoveries")
            ->selectRaw('COALESCE(SUM(ceat.debit), 0) as total_debits')
            ->selectRaw('COALESCE(SUM(ceat.credit), 0) as total_credits')
            ->first();

        $closingBalance = $openingBalance + (float) ($totalsData->total_debits ?? 0) - (float) ($totalsData->total_credits ?? 0);

        $summary = [
            'opening_balance' => $openingBalance,
            'total_credit_sales' => $totalsData->total_credit_sales ?? 0,
            'total_recoveries' => $totalsData->total_recoveries ?? 0,
            'total_debits' => $totalsData->total_debits ?? 0,
            'total_credits' => $totalsData->total_credits ?? 0,
            'closing_balance' => $closingBalance,
        ];

        // Get transaction types for filter
        $transactionTypes = DB::table('customer_employee_account_transactions as ceat')
            ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
            ->where('cea.employee_id', $employee->id)
            ->whereNull('ceat.deleted_at')
            ->distinct()
            ->pluck('ceat.transaction_type');

        return view('reports.credit-sales.salesman-details', [
            'employee' => $employee->load('supplier'),
            'entries' => $entries,
            'customerSummaries' => $customerSummaries,
            'customers' => $customers,
            'summary' => $summary,
            'transactionTypes' => $transactionTypes,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }
}
