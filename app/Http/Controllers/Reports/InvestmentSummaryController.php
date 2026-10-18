<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Supplier;
use App\Services\InvestmentSummaryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class InvestmentSummaryController extends Controller implements HasMiddleware
{
    public function __construct(private InvestmentSummaryService $summary) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:report-audit-investment-summary'),
        ];
    }

    public function index(Request $request)
    {
        $date = $request->input('date', now()->format('Y-m-d'));
        $canViewAllSuppliers = $this->canViewAllSuppliers();
        $userSupplierId = $this->getUserSupplierScope();
        $requestedSupplierId = $request->input('supplier_id');

        if ($requestedSupplierId && ! $canViewAllSuppliers && (int) $requestedSupplierId !== $userSupplierId) {
            abort(403, 'You do not have permission to filter by this supplier.');
        }

        $supplierId = $userSupplierId ?? ($requestedSupplierId ? (int) $requestedSupplierId : null);
        $hasSupplierSelection = $supplierId !== null;
        $designation = $request->input('designation', 'Salesman');
        $employeeIds = $request->input('employee_ids', []);
        $showBankSummary = $request->boolean('show_bank_summary', false);

        $suppliers = Supplier::query()
            ->where('disabled', false)
            ->when(! $canViewAllSuppliers, fn ($query) => $userSupplierId
                ? $query->where('id', $userSupplierId)
                : $query->whereRaw('1 = 0'))
            ->orderBy('supplier_name')
            ->get();
        $designations = Employee::query()
            ->distinct()
            ->whereNotNull('designation')
            ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
            ->when(! $supplierId && ! $canViewAllSuppliers, fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('designation')
            ->pluck('designation');

        $allEmployeesQuery = Employee::query();
        if ($supplierId) {
            $allEmployeesQuery->where('supplier_id', $supplierId);
        } else {
            $allEmployeesQuery->whereRaw('1 = 0');
        }
        if ($designation) {
            $allEmployeesQuery->where('designation', $designation);
        }
        $allEmployees = $allEmployeesQuery->orderBy('name')->get();

        $previousDate = Carbon::parse($date)->subDay()->format('Y-m-d');
        $lastDayPrevMonth = Carbon::parse($date)->subMonthNoOverflow()->endOfMonth()->toDateString();

        if ($hasSupplierSelection) {
            $salesmanCreditData = $this->summary->getSalesmanCreditData($date, $supplierId, $designation, $employeeIds);

            $creditGrandTotals = (object) [
                'opening_credit' => $salesmanCreditData->sum('opening_credit'),
                'credit_amount' => $salesmanCreditData->sum('credit_amount'),
                'recovery_amount' => $salesmanCreditData->sum('recovery_amount'),
                'total_credit' => $salesmanCreditData->sum('total_credit'),
            ];

            $powderExpiry = $this->summary->getPowderExpiry($supplierId, $date);
            $liquidExpiry = $this->summary->getLiquidExpiry($supplierId, $date);
            $claimAmount = $this->summary->getClaimAmount($supplierId, $date);
            $stockAmount = $this->summary->getStockAmount($supplierId, $date);
            $creditAmount = (float) $creditGrandTotals->total_credit;
            $ledgerAmount = $this->summary->getLedgerAmount($supplierId, $date);
            $currentTotal = $powderExpiry + $liquidExpiry + $claimAmount + $stockAmount + $creditAmount + $ledgerAmount;

            $previousCreditData = $this->summary->getSalesmanCreditData($previousDate, $supplierId, $designation, $employeeIds);
            $previousCreditAmount = (float) $previousCreditData->sum('total_credit');
            $previousPowderExpiry = $this->summary->getPowderExpiry($supplierId, $previousDate);
            $previousLiquidExpiry = $this->summary->getLiquidExpiry($supplierId, $previousDate);
            $previousStockAmount = $this->summary->getStockAmount($supplierId, $previousDate);
            $previousClaimAmount = $this->summary->getClaimAmount($supplierId, $previousDate);
            $previousLedgerAmount = $this->summary->getLedgerAmount($supplierId, $previousDate);
            $previousTotalInvestment = $previousPowderExpiry + $previousLiquidExpiry + $previousClaimAmount + $previousStockAmount + $previousCreditAmount + $previousLedgerAmount;

            $dailyCash = $this->summary->getDailyCash($date, $supplierId);
            $dailyBankSlips = $this->summary->getDailyBankSlips($date, $supplierId);
            $dailyBankTransfers = $this->summary->getDailyBankTransfers($date, $supplierId);
            $dailyChequePayments = $this->summary->getDailyChequePayments($date, $supplierId);
            $totalInvestment = $currentTotal + $dailyCash + $dailyBankSlips + $dailyBankTransfers + $dailyChequePayments;
            $bankOnline = $this->summary->getBankOnline($date, $supplierId);
            $increaseInInvestment = $totalInvestment - $previousTotalInvestment - $bankOnline;

            $openingBalanceData = $this->summary->getOpeningBalanceValues($date, $supplierId);
            $expenseCategoryTotals = [];

            if ($openingBalanceData !== null) {
                $bankOpeningAmount = $openingBalanceData['BANK_OPENING_AMOUNT'] ?? 0.0;
                $totalCashReceivedMonth = $openingBalanceData['TOTAL_CASH_RECEIVED_CURRENT_MONTH'] ?? 0.0;
                $totalBankAmount = $totalCashReceivedMonth + $bankOpeningAmount;
                $totalOnlineAmountMonth = $openingBalanceData['TOTAL_ONLINE_AMOUNT_CURRENT_MONTH'] ?? 0.0;
                $closingBalanceBeforeExpenses = $openingBalanceData['CLOSING_BALANCE_BEFORE_EXPENSES'] ?? 0.0;
                $totalExpensesMonth = $openingBalanceData['TOTAL_EXPENSE_CURRENT_MONTH'] ?? 0.0;
                $closingBalanceAfterExpenses = $openingBalanceData['CLOSING_BALANCE_AFTER_EXPENSE'] ?? 0.0;
                $lastMonthMainInvestment = (float) ($openingBalanceData['LAST_MONTH_MAIN_INVESTMENT']
                    ?? $this->summary->getMainInvestmentTotal($lastDayPrevMonth, $supplierId, $designation, $employeeIds));
            } else {
                $bankOpeningAmount = 0.0;
                $totalCashReceivedMonth = $this->summary->getMonthlyCashReceived($date, $supplierId);
                $totalBankAmount = $totalCashReceivedMonth + $bankOpeningAmount;
                $totalOnlineAmountMonth = $this->summary->getMonthlyOnlineAmount($date, $supplierId);
                $closingBalanceBeforeExpenses = $totalBankAmount - $totalOnlineAmountMonth;
                $expenseCategoryTotals = $this->summary->getMonthlyExpenses($date, $supplierId);
                $totalExpensesMonth = array_sum($expenseCategoryTotals);
                $closingBalanceAfterExpenses = $closingBalanceBeforeExpenses - $totalExpensesMonth;
                $lastMonthMainInvestment = $this->summary->getSavedMainInvestment($lastDayPrevMonth, $supplierId)
                    ?? $this->summary->getMainInvestmentTotal($lastDayPrevMonth, $supplierId, $designation, $employeeIds);
            }
        } else {
            $salesmanCreditData = collect();
            $creditGrandTotals = (object) [
                'opening_credit' => 0.0,
                'credit_amount' => 0.0,
                'recovery_amount' => 0.0,
                'total_credit' => 0.0,
            ];
            $powderExpiry = 0.0;
            $liquidExpiry = 0.0;
            $claimAmount = 0.0;
            $stockAmount = 0.0;
            $creditAmount = 0.0;
            $ledgerAmount = 0.0;
            $currentTotal = 0.0;
            $previousTotalInvestment = 0.0;
            $dailyCash = 0.0;
            $dailyBankSlips = 0.0;
            $dailyBankTransfers = 0.0;
            $dailyChequePayments = 0.0;
            $totalInvestment = 0.0;
            $bankOnline = 0.0;
            $increaseInInvestment = 0.0;
            $bankOpeningAmount = 0.0;
            $totalCashReceivedMonth = 0.0;
            $totalBankAmount = 0.0;
            $totalOnlineAmountMonth = 0.0;
            $closingBalanceBeforeExpenses = 0.0;
            $expenseCategoryTotals = [];
            $totalExpensesMonth = 0.0;
            $closingBalanceAfterExpenses = 0.0;
            $lastMonthMainInvestment = 0.0;
        }

        $currentMonthMainInvestment = $currentTotal;
        $netInvestment = $closingBalanceAfterExpenses + $currentMonthMainInvestment;
        $increaseInInvestmentMonth = $netInvestment - $lastMonthMainInvestment;

        $selectedSupplier = $supplierId ? $suppliers->find($supplierId) : null;
        $formattedDate = Carbon::parse($date)->format('d.m.Y');
        $formattedPreviousDate = Carbon::parse($previousDate)->format('d.m.Y');
        $formattedLastDayPrevMonth = Carbon::parse($lastDayPrevMonth)->format('d.m.Y');
        $currentMonthName = Carbon::parse($date)->format('F Y');

        return view('reports.investment-summary', compact(
            'date',
            'supplierId',
            'designation',
            'employeeIds',
            'suppliers',
            'designations',
            'allEmployees',
            'salesmanCreditData',
            'creditGrandTotals',
            'powderExpiry',
            'liquidExpiry',
            'claimAmount',
            'stockAmount',
            'creditAmount',
            'ledgerAmount',
            'currentTotal',
            'previousDate',
            'previousTotalInvestment',
            'dailyCash',
            'dailyBankSlips',
            'dailyBankTransfers',
            'dailyChequePayments',
            'totalInvestment',
            'bankOnline',
            'increaseInInvestment',
            'bankOpeningAmount',
            'totalCashReceivedMonth',
            'totalBankAmount',
            'totalOnlineAmountMonth',
            'closingBalanceBeforeExpenses',
            'totalExpensesMonth',
            'expenseCategoryTotals',
            'closingBalanceAfterExpenses',
            'lastMonthMainInvestment',
            'currentMonthMainInvestment',
            'netInvestment',
            'increaseInInvestmentMonth',
            'selectedSupplier',
            'formattedDate',
            'formattedPreviousDate',
            'formattedLastDayPrevMonth',
            'currentMonthName',
            'showBankSummary',
            'canViewAllSuppliers',
            'hasSupplierSelection',
        ));
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
}
