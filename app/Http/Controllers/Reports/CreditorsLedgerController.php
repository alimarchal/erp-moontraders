<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Employee;
use App\Services\LedgerService;
use Illuminate\Http\Request;

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

        if ($request->filled('filter.city')) {
            $customersQuery->where('city', 'like', '%'.$request->input('filter.city').'%');
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

        $totals = CustomerLedger::query()
            ->selectRaw('SUM(debit) as total_debits, SUM(credit) as total_credits')
            ->first();

        $cities = Customer::distinct('city')->whereNotNull('city')->pluck('city')->sort();

        return view('reports.creditors-ledger.index', [
            'customers' => $customers,
            'totals' => $totals,
            'cities' => $cities,
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

        $entriesQuery = CustomerLedger::where('customer_id', $customer->id)
            ->with(['employee', 'salesSettlement', 'creditSale']);

        if ($dateFrom) {
            $entriesQuery->whereDate('transaction_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $entriesQuery->whereDate('transaction_date', '<=', $dateTo);
        }

        if ($request->filled('filter.transaction_type')) {
            $entriesQuery->where('transaction_type', $request->input('filter.transaction_type'));
        }

        $entries = $entriesQuery->orderBy('transaction_date')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        $openingBalance = 0;
        if ($dateFrom) {
            $openingBalance = CustomerLedger::where('customer_id', $customer->id)
                ->where('transaction_date', '<', $dateFrom)
                ->orderBy('transaction_date', 'desc')
                ->orderBy('id', 'desc')
                ->value('balance') ?? 0;
        }

        $summary = [
            'opening_balance' => $openingBalance,
            'total_debits' => $entries->sum('debit'),
            'total_credits' => $entries->sum('credit'),
            'closing_balance' => $this->ledgerService->getCustomerBalance($customer->id),
        ];

        $transactionTypes = CustomerLedger::distinct('transaction_type')->pluck('transaction_type');

        return view('reports.creditors-ledger.customer-ledger', [
            'customer' => $customer,
            'entries' => $entries,
            'summary' => $summary,
            'transactionTypes' => $transactionTypes,
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
            ->select('ceat.*', 'cea.employee_id', 'ss.settlement_number', 'ss.settlement_date')
            ->from('customer_employee_account_transactions as ceat')
            ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
            ->leftJoin('sales_settlements as ss', 'ceat.sales_settlement_id', '=', 'ss.id')
            ->where('cea.customer_id', $customer->id)
            ->where('ceat.transaction_type', 'credit_sale')
            ->with(['account.employee', 'salesSettlement']);

        if ($request->filled('filter.date_from')) {
            $creditSalesQuery->whereDate('ceat.transaction_date', '>=', $request->input('filter.date_from'));
        }

        if ($request->filled('filter.date_to')) {
            $creditSalesQuery->whereDate('ceat.transaction_date', '<=', $request->input('filter.date_to'));
        }

        $creditSales = $creditSalesQuery->orderByDesc('ceat.transaction_date')
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
