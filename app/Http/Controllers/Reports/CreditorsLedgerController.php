<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Employee;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreditorsLedgerController extends Controller
{
    public function __construct(protected LedgerService $ledgerService) {}

    /**
     * Display creditors (accounts receivable) ledger summary
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 50);
        $perPage = in_array($perPage, [10, 25, 50, 100, 250]) ? $perPage : 50;

        $customersQuery = Customer::query()
            ->whereHas('ledgerEntries')
            ->withCount('ledgerEntries')
            ->withSum('ledgerEntries as total_debits', 'debit')
            ->withSum('ledgerEntries as total_credits', 'credit');

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

        if ($request->filled('filter.channel_type')) {
            $customersQuery->where('channel_type', $request->input('filter.channel_type'));
        }

        if ($request->filled('filter.is_active')) {
            $customersQuery->where('is_active', $request->input('filter.is_active'));
        }

        if ($request->filled('filter.balance_min')) {
            $customersQuery->havingRaw('(COALESCE(total_debits, 0) - COALESCE(total_credits, 0)) >= ?', [$request->input('filter.balance_min')]);
        }

        if ($request->filled('filter.balance_max')) {
            $customersQuery->havingRaw('(COALESCE(total_debits, 0) - COALESCE(total_credits, 0)) <= ?', [$request->input('filter.balance_max')]);
        }

        $sort = $request->input('sort', '-total_debits');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (in_array($column, ['customer_name', 'customer_code', 'city', 'total_debits', 'total_credits', 'ledger_entries_count'])) {
            $customersQuery->orderBy($column, $direction);
        } else {
            $customersQuery->orderByRaw('(COALESCE(total_debits, 0) - COALESCE(total_credits, 0)) DESC');
        }

        $customers = $customersQuery->paginate($perPage)->withQueryString();

        // Calculate totals from customer_employee_account_transactions
        $totals = DB::table('customer_employee_account_transactions')
            ->whereNull('deleted_at')
            ->selectRaw('SUM(debit) as total_debits, SUM(credit) as total_credits')
            ->first();

        $cities = Customer::whereNotNull('city')->distinct()->pluck('city')->sort();
        $channelTypes = Customer::whereNotNull('channel_type')->distinct()->pluck('channel_type')->sort();

        return view('reports.creditors-ledger.index', [
            'customers' => $customers,
            'totals' => $totals,
            'cities' => $cities,
            'channelTypes' => $channelTypes,
        ]);
    }

    /**
     * Display detailed ledger for a specific customer
     */
    public function customerLedger(Request $request, Customer $customer)
    {
        $perPage = $request->input('per_page', 100);
        $perPage = in_array($perPage, [10, 25, 50, 100, 250]) ? $perPage : 100;

        $dateFrom = $request->input('filter.date_from');
        $dateTo = $request->input('filter.date_to');

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

        if ($dateFrom) {
            $entriesQuery->whereDate('ceat.transaction_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $entriesQuery->whereDate('ceat.transaction_date', '<=', $dateTo);
        }

        if ($request->filled('filter.transaction_type')) {
            $entriesQuery->where('ceat.transaction_type', $request->input('filter.transaction_type'));
        }

        if ($request->filled('filter.reference_number')) {
            $entriesQuery->where('ceat.reference_number', 'like', '%'.$request->input('filter.reference_number').'%');
        }

        if ($request->filled('filter.employee_id')) {
            $entriesQuery->where('cea.employee_id', $request->input('filter.employee_id'));
        }

        $entries = $entriesQuery->orderBy('ceat.transaction_date')
            ->orderBy('ceat.id')
            ->paginate($perPage)
            ->withQueryString();

        // Calculate opening balance (all transactions before dateFrom or before current page)
        $openingBalance = 0;
        if ($dateFrom) {
            $openingBalanceResult = DB::table('customer_employee_account_transactions as ceat')
                ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
                ->where('cea.customer_id', $customer->id)
                ->where('ceat.transaction_date', '<', $dateFrom)
                ->whereNull('ceat.deleted_at')
                ->selectRaw('COALESCE(SUM(ceat.debit), 0) - COALESCE(SUM(ceat.credit), 0) as balance')
                ->first();

            $openingBalance = $openingBalanceResult ? (float) $openingBalanceResult->balance : 0;
        }

        // Calculate balance before current page (for pagination)
        $balanceBeforePage = $openingBalance;
        if ($entries->currentPage() > 1) {
            // Get sum of all entries before current page
            $beforePageQuery = DB::table('customer_employee_account_transactions as ceat')
                ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
                ->where('cea.customer_id', $customer->id)
                ->whereNull('ceat.deleted_at');

            if ($dateFrom) {
                $beforePageQuery->whereDate('ceat.transaction_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $beforePageQuery->whereDate('ceat.transaction_date', '<=', $dateTo);
            }
            if ($request->filled('filter.transaction_type')) {
                $beforePageQuery->where('ceat.transaction_type', $request->input('filter.transaction_type'));
            }
            if ($request->filled('filter.reference_number')) {
                $beforePageQuery->where('ceat.reference_number', 'like', '%'.$request->input('filter.reference_number').'%');
            }
            if ($request->filled('filter.employee_id')) {
                $beforePageQuery->where('cea.employee_id', $request->input('filter.employee_id'));
            }

            $entriesBeforePage = ($entries->currentPage() - 1) * $perPage;
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
            $runningBalance += (float) ($entry->debit ?? 0) - (float) ($entry->credit ?? 0);
            $entry->balance = $runningBalance;

            return $entry;
        });

        $summary = [
            'opening_balance' => $openingBalance,
            'total_debits' => $entries->sum('debit'),
            'total_credits' => $entries->sum('credit'),
            'closing_balance' => $this->ledgerService->getCustomerBalance($customer->id),
        ];

        // Get transaction types from the actual transactions
        $transactionTypes = DB::table('customer_employee_account_transactions')
            ->distinct()
            ->whereNull('deleted_at')
            ->pluck('transaction_type');

        // Get employees for filter dropdown
        $employees = Employee::whereHas('customerAccounts', function ($q) use ($customer) {
            $q->where('customer_id', $customer->id);
        })->orderBy('name')->get();

        return view('reports.creditors-ledger.customer-ledger', [
            'customer' => $customer,
            'entries' => $entries,
            'summary' => $summary,
            'transactionTypes' => $transactionTypes,
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
        $creditSalesQuery = \App\Models\CustomerEmployeeAccountTransaction::query()
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
        $salesmenBreakdown = \Illuminate\Support\Facades\DB::table('customer_employee_account_transactions as ceat')
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
