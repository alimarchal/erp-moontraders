<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\ClaimRegister;
use App\Models\Employee;
use App\Models\ExpenseDetail;
use App\Models\LedgerRegister;
use App\Models\SalesSettlement;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InvestmentSummaryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-audit-investment-summary'),
        ];
    }

    public function index(Request $request)
    {
        $date = $request->input('date', now()->format('Y-m-d'));
        $supplierId = $request->input('supplier_id', Supplier::where('supplier_name', 'Nestlé Pakistan')->value('id'));
        $designation = $request->input('designation', 'Salesman');
        $employeeIds = $request->input('employee_ids', []);
        $showBankSummary = $request->boolean('show_bank_summary', false);

        $suppliers = Supplier::orderBy('supplier_name')->get();
        $designations = Employee::distinct()->whereNotNull('designation')->orderBy('designation')->pluck('designation');

        $allEmployeesQuery = Employee::query();
        if ($supplierId) {
            $allEmployeesQuery->where('supplier_id', $supplierId);
        }
        if ($designation) {
            $allEmployeesQuery->where('designation', $designation);
        }
        $allEmployees = $allEmployeesQuery->orderBy('name')->get();

        // Part 1: Salesman Credit Data
        $salesmanCreditData = $this->getSalesmanCreditData($date, $supplierId, $designation, $employeeIds);

        // Grand totals
        $creditGrandTotals = (object) [
            'opening_credit' => $salesmanCreditData->sum('opening_credit'),
            'credit_amount' => $salesmanCreditData->sum('credit_amount'),
            'recovery_amount' => $salesmanCreditData->sum('recovery_amount'),
            'total_credit' => $salesmanCreditData->sum('total_credit'),
        ];

        // Part 2: Investment Summary
        $powderExpiry = $this->getPowderExpiry($supplierId, $date);
        $liquidExpiry = $this->getLiquidExpiry($supplierId, $date);
        $claimAmount = $this->getClaimAmount($supplierId, $date);
        $stockAmount = $this->getStockAmount($supplierId, $date);
        $creditAmount = (float) $creditGrandTotals->total_credit;
        $ledgerAmount = $this->getLedgerAmount($supplierId, $date);

        $currentTotal = $powderExpiry + $liquidExpiry + $claimAmount + $stockAmount + $creditAmount + $ledgerAmount;

        // Previous date comparison
        $previousDate = Carbon::parse($date)->subDay()->format('Y-m-d');
        $previousCreditData = $this->getSalesmanCreditData($previousDate, $supplierId, $designation, $employeeIds);
        $previousCreditAmount = (float) $previousCreditData->sum('total_credit');
        $previousPowderExpiry = $this->getPowderExpiry($supplierId, $previousDate);
        $previousLiquidExpiry = $this->getLiquidExpiry($supplierId, $previousDate);
        $previousStockAmount = $this->getStockAmount($supplierId, $previousDate);
        $previousClaimAmount = $this->getClaimAmount($supplierId, $previousDate);
        $previousLedgerAmount = $this->getLedgerAmount($supplierId, $previousDate);

        $previousTotal = $previousPowderExpiry + $previousLiquidExpiry + $previousClaimAmount + $previousStockAmount + $previousCreditAmount + $previousLedgerAmount;

        // Daily Cash & Investment calculations
        $dailyCash = $this->getDailyCash($date, $supplierId);
        $totalInvestment = $currentTotal + $dailyCash;
        $bankOnline = $this->getBankOnline($date, $supplierId);
        $increaseInInvestment = $totalInvestment - $previousTotal - $bankOnline;

        // Bank/Cash summary
        $bankOpeningAmount = 0;
        $totalCashReceivedMonth = $this->getMonthlyDailyCash($date, $supplierId);
        $totalBankAmount = $totalCashReceivedMonth + $bankOpeningAmount;
        $totalOnlineAmountMonth = $this->getMonthlyOnlineAmount($date, $supplierId);
        $closingBalanceBeforeExpenses = $totalBankAmount - $totalOnlineAmountMonth;
        $expenseCategoryTotals = $this->getMonthlyExpenses($date, $supplierId);
        $totalExpensesMonth = array_sum($expenseCategoryTotals);
        $closingBalanceAfterExpenses = $closingBalanceBeforeExpenses - $totalExpensesMonth;

        // Last month main investment
        $lastDayPrevMonth = Carbon::parse($date)->subMonthNoOverflow()->endOfMonth()->toDateString();
        $lastMonthCreditData = $this->getSalesmanCreditData($lastDayPrevMonth, $supplierId, $designation, $employeeIds);
        $lastMonthMainInvestment = $this->getPowderExpiry($supplierId, $lastDayPrevMonth)
            + $this->getLiquidExpiry($supplierId, $lastDayPrevMonth)
            + $this->getClaimAmount($supplierId, $lastDayPrevMonth)
            + $this->getStockAmount($supplierId, $lastDayPrevMonth)
            + (float) $lastMonthCreditData->sum('total_credit')
            + $this->getLedgerAmount($supplierId, $lastDayPrevMonth);

        $currentMonthMainInvestment = $currentTotal;
        $netInvestment = $closingBalanceBeforeExpenses - $currentMonthMainInvestment;
        $increaseInInvestmentMonth = $lastMonthMainInvestment - $netInvestment;

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
            'previousTotal',
            'dailyCash',
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
        ));
    }

    private function getSalesmanCreditData(string $date, ?int $supplierId, ?string $designation, array $employeeIds)
    {
        $query = Employee::query()
            ->select('employees.*')
            ->selectSub(function ($query) use ($date) {
                $query->from('customer_employee_account_transactions as ceat')
                    ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
                    ->whereColumn('cea.employee_id', 'employees.id')
                    ->where(function ($q) use ($date) {
                        $q->where('ceat.transaction_date', '<', $date)
                            ->orWhere(function ($q2) use ($date) {
                                $q2->where('ceat.transaction_date', '=', $date)
                                    ->where('ceat.transaction_type', 'opening_balance');
                            });
                    })
                    ->whereNull('ceat.deleted_at')
                    ->selectRaw('COALESCE(SUM(ceat.debit), 0) - COALESCE(SUM(ceat.credit), 0)');
            }, 'opening_credit')
            ->selectSub(function ($query) use ($date) {
                $query->from('customer_employee_account_transactions as ceat')
                    ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
                    ->whereColumn('cea.employee_id', 'employees.id')
                    ->where('ceat.transaction_type', 'credit_sale')
                    ->where('ceat.transaction_date', $date)
                    ->whereNull('ceat.deleted_at')
                    ->selectRaw('COALESCE(SUM(ceat.debit), 0)');
            }, 'credit_amount')
            ->selectSub(function ($query) use ($date) {
                $query->from('customer_employee_account_transactions as ceat')
                    ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
                    ->whereColumn('cea.employee_id', 'employees.id')
                    ->where('ceat.transaction_type', 'recovery')
                    ->where('ceat.transaction_date', $date)
                    ->whereNull('ceat.deleted_at')
                    ->selectRaw('COALESCE(SUM(ceat.credit), 0)');
            }, 'recovery_amount');

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        if ($designation) {
            $query->where('designation', $designation);
        }

        if (! empty($employeeIds)) {
            $query->whereIn('employees.id', $employeeIds);
        }

        $query->orderBy('name');

        return $query->get()->map(function ($employee) {
            $employee->opening_credit = (float) $employee->opening_credit;
            $employee->credit_amount = (float) $employee->credit_amount;
            $employee->recovery_amount = (float) $employee->recovery_amount;
            $employee->total_credit = $employee->opening_credit + $employee->credit_amount - $employee->recovery_amount;

            return $employee;
        });
    }

    private function getPowderExpiry(?int $supplierId, ?string $date = null): float
    {
        $asOfDate = $date ?? now()->toDateString();
        $asOfDateTime = Carbon::parse($asOfDate)->endOfDay();
        $hasDisposedAt = Schema::hasColumn('sales_settlement_amr_powders', 'disposed_at');

        $query = DB::table('sales_settlement_amr_powders as p')
            ->join('sales_settlements as ss', 'p.sales_settlement_id', '=', 'ss.id')
            ->whereDate('ss.settlement_date', '<=', $asOfDate)
            ->whereNull('ss.deleted_at');

        if ($hasDisposedAt) {
            $query->where(function ($q) use ($asOfDateTime) {
                $q->where('p.is_disposed', false)
                    ->orWhere(function ($q2) use ($asOfDateTime) {
                        $q2->where('p.is_disposed', true)
                            ->where(function ($q3) use ($asOfDateTime) {
                                $q3->where('p.disposed_at', '>', $asOfDateTime)
                                    ->orWhere(function ($q4) use ($asOfDateTime) {
                                        $q4->whereNull('p.disposed_at')
                                            ->where('p.updated_at', '>', $asOfDateTime);
                                    });
                            });
                    });
            });
        } else {
            $query->where(function ($q) use ($asOfDateTime) {
                $q->where('p.is_disposed', false)
                    ->orWhere(function ($q2) use ($asOfDateTime) {
                        $q2->where('p.is_disposed', true)
                            ->where('p.updated_at', '>', $asOfDateTime);
                    });
            });
        }

        if ($supplierId) {
            $query->where('ss.supplier_id', $supplierId);
        }

        return (float) $query->sum('p.amount');
    }

    private function getLiquidExpiry(?int $supplierId, ?string $date = null): float
    {
        $asOfDate = $date ?? now()->toDateString();
        $asOfDateTime = Carbon::parse($asOfDate)->endOfDay();
        $hasDisposedAt = Schema::hasColumn('sales_settlement_amr_liquids', 'disposed_at');

        $query = DB::table('sales_settlement_amr_liquids as l')
            ->join('sales_settlements as ss', 'l.sales_settlement_id', '=', 'ss.id')
            ->whereDate('ss.settlement_date', '<=', $asOfDate)
            ->whereNull('ss.deleted_at');

        if ($hasDisposedAt) {
            $query->where(function ($q) use ($asOfDateTime) {
                $q->where('l.is_disposed', false)
                    ->orWhere(function ($q2) use ($asOfDateTime) {
                        $q2->where('l.is_disposed', true)
                            ->where(function ($q3) use ($asOfDateTime) {
                                $q3->where('l.disposed_at', '>', $asOfDateTime)
                                    ->orWhere(function ($q4) use ($asOfDateTime) {
                                        $q4->whereNull('l.disposed_at')
                                            ->where('l.updated_at', '>', $asOfDateTime);
                                    });
                            });
                    });
            });
        } else {
            $query->where(function ($q) use ($asOfDateTime) {
                $q->where('l.is_disposed', false)
                    ->orWhere(function ($q2) use ($asOfDateTime) {
                        $q2->where('l.is_disposed', true)
                            ->where('l.updated_at', '>', $asOfDateTime);
                    });
            });
        }

        if ($supplierId) {
            $query->where('ss.supplier_id', $supplierId);
        }

        return (float) $query->sum('l.amount');
    }

    private function getClaimAmount(?int $supplierId, ?string $date = null): float
    {
        $query = ClaimRegister::whereNotNull('posted_at');

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        if ($date) {
            $query->where('transaction_date', '<=', $date);
        }

        return (float) $query->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as balance')
            ->value('balance');
    }

    private function getStockAmount(?int $supplierId, ?string $date = null): float
    {
        $today = now()->format('Y-m-d');

        // For today or future dates, use current stock
        if (! $date || $date >= $today) {
            $query = DB::table('current_stock_by_batch as csb')
                ->join('products as p', 'csb.product_id', '=', 'p.id')
                ->where('csb.status', 'active')
                ->whereNull('p.deleted_at');

            if ($supplierId) {
                $query->where('p.supplier_id', $supplierId);
            }

            return (float) $query->selectRaw('COALESCE(SUM(csb.total_value), 0) as total')
                ->value('total');
        }

        // For past dates, use daily inventory snapshots
        $query = DB::table('daily_inventory_snapshots as dis')
            ->join('products as p', 'dis.product_id', '=', 'p.id')
            ->where('dis.date', $date)
            ->whereNull('p.deleted_at');

        if ($supplierId) {
            $query->where('p.supplier_id', $supplierId);
        }

        $snapshotCount = (clone $query)->count();

        if ($snapshotCount > 0) {
            return (float) $query->selectRaw('COALESCE(SUM(dis.total_value), 0) as total')
                ->value('total');
        }

        // Fallback: if snapshot job has not populated this date yet,
        // derive stock value as-of date from stock ledger running balances.
        return $this->getHistoricalStockFromLedger($supplierId, $date);
    }

    private function getHistoricalStockFromLedger(?int $supplierId, string $date): float
    {
        $latestEntryIds = DB::table('stock_ledger_entries as sle')
            ->join('products as p', 'sle.product_id', '=', 'p.id')
            ->whereDate('sle.entry_date', '<=', $date)
            ->whereNull('p.deleted_at');

        if ($supplierId) {
            $latestEntryIds->where('p.supplier_id', $supplierId);
        }

        $latestEntryIds->selectRaw('MAX(sle.id) as latest_id')
            ->groupBy('sle.product_id', 'sle.warehouse_id', 'sle.stock_batch_id');

        return (float) DB::table('stock_ledger_entries as sle')
            ->joinSub($latestEntryIds, 'latest', function ($join) {
                $join->on('sle.id', '=', 'latest.latest_id');
            })
            ->selectRaw('COALESCE(SUM(sle.stock_value), 0) as total')
            ->value('total');
    }

    private function getLedgerAmount(?int $supplierId, ?string $date = null): float
    {
        $query = LedgerRegister::whereNotNull('posted_at');

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        if ($date) {
            $query->where('transaction_date', '<=', $date);
        }

        return (float) $query->selectRaw(
            'COALESCE(SUM(online_amount - invoice_amount - expenses_amount + za_point_five_percent_amount + claim_adjust_amount), 0) as balance'
        )->value('balance');
    }

    private function getDailyCash(string $date, ?int $supplierId): float
    {
        $query = SalesSettlement::with('cashDenominations')
            ->whereDate('settlement_date', $date);

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        return (float) $query->get()->sum(function ($settlement) {
            $cashDenom = $settlement->cashDenominations->first();

            return ($cashDenom && $cashDenom->total_amount > 0)
                ? (float) $cashDenom->total_amount
                : (float) $settlement->cash_collected;
        });
    }

    private function getBankOnline(string $date, ?int $supplierId): float
    {
        $query = LedgerRegister::whereNotNull('posted_at')
            ->where('transaction_date', $date);

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        return (float) $query->sum('online_amount');
    }

    private function getMonthlyDailyCash(string $upToDate, ?int $supplierId): float
    {
        $startOfMonth = Carbon::parse($upToDate)->startOfMonth()->toDateString();

        $query = SalesSettlement::with('cashDenominations')
            ->whereBetween('settlement_date', [$startOfMonth, $upToDate]);

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        return (float) $query->get()->sum(function ($settlement) {
            $cashDenom = $settlement->cashDenominations->first();

            return ($cashDenom && $cashDenom->total_amount > 0)
                ? (float) $cashDenom->total_amount
                : (float) $settlement->cash_collected;
        });
    }

    /**
     * @return array<string, float>
     */
    /**
     * @return array<string, float>
     */
    private function getMonthlyExpenses(string $upToDate, ?int $supplierId = null): array
    {
        $startOfMonth = Carbon::parse($upToDate)->startOfMonth()->toDateString();

        $categories = ['stationary', 'tcs', 'tonner_it', 'salaries', 'fuel', 'van_work'];
        $totals = [];

        foreach ($categories as $category) {
            $query = ExpenseDetail::where('category', $category)
                ->whereNotNull('posted_at')
                ->whereBetween('transaction_date', [$startOfMonth, $upToDate]);

            if ($supplierId) {
                $query->where('supplier_id', $supplierId);
            }

            $totals[$category] = (float) $query->sum('amount');
        }

        return $totals;
    }

    private function getMonthlyOnlineAmount(string $upToDate, ?int $supplierId): float
    {
        $startOfMonth = Carbon::parse($upToDate)->startOfMonth()->toDateString();

        $query = LedgerRegister::whereNotNull('posted_at')
            ->whereBetween('transaction_date', [$startOfMonth, $upToDate]);

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        return (float) $query->sum('online_amount');
    }
}
