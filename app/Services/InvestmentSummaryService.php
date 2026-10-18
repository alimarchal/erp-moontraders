<?php

namespace App\Services;

use App\Models\ClaimRegister;
use App\Models\Employee;
use App\Models\ExpenseDetail;
use App\Models\InvestmentOpeningBalance;
use App\Models\LedgerRegister;
use App\Models\SalesSettlement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Figures behind the Investment Summary report (reports/investment-summary), kept in
 * one place so the report and the dashboard show exactly the same numbers.
 */
class InvestmentSummaryService
{
    public function getSalesmanCreditData(string $date, ?int $supplierId, ?string $designation, array $employeeIds)
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
                                    ->whereNotIn('ceat.transaction_type', ['credit_sale', 'recovery']);
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

    public function getPowderExpiry(?int $supplierId, ?string $date = null): float
    {
        $asOfDate = $date ?? now()->toDateString();
        $startOfMonth = Carbon::parse($asOfDate)->startOfMonth()->toDateString();
        $asOfDateTime = Carbon::parse($asOfDate)->endOfDay();

        $query = DB::table('sales_settlement_amr_powders as p')
            ->join('sales_settlements as ss', 'p.sales_settlement_id', '=', 'ss.id')
            ->where('ss.status', 'posted')
            ->whereBetween('ss.settlement_date', [$startOfMonth, $asOfDate])
            ->whereNull('ss.deleted_at')
            ->where(function ($q) use ($asOfDateTime) {
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

        if ($supplierId) {
            $query->where('ss.supplier_id', $supplierId);
        }

        return (float) $query->sum('p.amount');
    }

    public function getLiquidExpiry(?int $supplierId, ?string $date = null): float
    {
        $asOfDate = $date ?? now()->toDateString();
        $startOfMonth = Carbon::parse($asOfDate)->startOfMonth()->toDateString();
        $asOfDateTime = Carbon::parse($asOfDate)->endOfDay();

        $query = DB::table('sales_settlement_amr_liquids as l')
            ->join('sales_settlements as ss', 'l.sales_settlement_id', '=', 'ss.id')
            ->where('ss.status', 'posted')
            ->whereBetween('ss.settlement_date', [$startOfMonth, $asOfDate])
            ->whereNull('ss.deleted_at')
            ->where(function ($q) use ($asOfDateTime) {
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

        if ($supplierId) {
            $query->where('ss.supplier_id', $supplierId);
        }

        return (float) $query->sum('l.amount');
    }

    public function getClaimAmount(?int $supplierId, ?string $date = null): float
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

    public function getStockAmount(?int $supplierId, ?string $date = null): float
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

        // For past dates, use daily inventory snapshots. Warehouse rows only, so a past date
        // is measured the same way as today above, which reads warehouse stock from
        // current_stock_by_batch. The table also carries vehicle rows (warehouse_id null),
        // and counting those here would make history jump above the live figure.
        $query = DB::table('daily_inventory_snapshots as dis')
            ->join('products as p', 'dis.product_id', '=', 'p.id')
            ->where('dis.date', $date)
            ->whereNotNull('dis.warehouse_id')
            ->whereNull('dis.vehicle_id')
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

    public function getHistoricalStockFromLedger(?int $supplierId, string $date): float
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

    public function getLedgerAmount(?int $supplierId, ?string $date = null): float
    {
        $query = LedgerRegister::whereNotNull('posted_at');

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        if ($date) {
            $query->where('transaction_date', '<=', $date);
        }

        return (float) $query->selectRaw(
            'COALESCE(SUM(opening_balance + online_amount - invoice_amount - expenses_amount + za_point_five_percent_amount + claim_adjust_amount), 0) as balance'
        )->value('balance');
    }

    public function getDailyCash(string $date, ?int $supplierId): float
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

    public function getBankOnline(string $date, ?int $supplierId): float
    {
        $query = LedgerRegister::whereNotNull('posted_at')
            ->whereDate('transaction_date', $date);

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        return (float) $query->sum('online_amount');
    }

    public function getMonthlyCashReceived(string $upToDate, ?int $supplierId): float
    {
        $startOfMonth = Carbon::parse($upToDate)->startOfMonth()->toDateString();

        $cashQuery = SalesSettlement::with('cashDenominations')
            ->whereBetween('settlement_date', [$startOfMonth, $upToDate]);

        if ($supplierId) {
            $cashQuery->where('supplier_id', $supplierId);
        }

        $cashTotal = (float) $cashQuery->get()->sum(function ($settlement) {
            $cashDenom = $settlement->cashDenominations->first();

            return ($cashDenom && $cashDenom->total_amount > 0)
                ? (float) $cashDenom->total_amount
                : (float) $settlement->cash_collected;
        });

        $slipsQuery = DB::table('sales_settlement_bank_slips as bs')
            ->join('sales_settlements as ss', 'bs.sales_settlement_id', '=', 'ss.id')
            ->whereBetween('ss.settlement_date', [$startOfMonth, $upToDate])
            ->whereNull('ss.deleted_at');

        if ($supplierId) {
            $slipsQuery->where('ss.supplier_id', $supplierId);
        }

        $transfersQuery = DB::table('sales_settlement_bank_transfers as bt')
            ->join('sales_settlements as ss', 'bt.sales_settlement_id', '=', 'ss.id')
            ->whereBetween('ss.settlement_date', [$startOfMonth, $upToDate])
            ->whereNull('ss.deleted_at');

        if ($supplierId) {
            $transfersQuery->where('ss.supplier_id', $supplierId);
        }

        $recoveryTransfersQuery = DB::table('sales_settlement_recoveries as sr')
            ->join('sales_settlements as ss', 'sr.sales_settlement_id', '=', 'ss.id')
            ->whereBetween('ss.settlement_date', [$startOfMonth, $upToDate])
            ->whereNull('ss.deleted_at')
            ->whereNull('sr.deleted_at')
            ->where('sr.payment_method', 'bank_transfer');

        if ($supplierId) {
            $recoveryTransfersQuery->where('ss.supplier_id', $supplierId);
        }

        $chequesQuery = DB::table('sales_settlement_cheques as sc')
            ->join('sales_settlements as ss', 'sc.sales_settlement_id', '=', 'ss.id')
            ->whereBetween('ss.settlement_date', [$startOfMonth, $upToDate])
            ->whereNull('ss.deleted_at');

        if ($supplierId) {
            $chequesQuery->where('ss.supplier_id', $supplierId);
        }

        return $cashTotal
            + (float) $slipsQuery->sum('bs.amount')
            + (float) $transfersQuery->sum('bt.amount')
            + (float) $recoveryTransfersQuery->sum('sr.amount')
            + (float) $chequesQuery->sum('sc.amount');
    }

    /**
     * @return array<string, float>
     */
    /**
     * @return array<string, float>
     */
    public function getMonthlyExpenses(string $upToDate, ?int $supplierId = null): array
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

    public function getMonthlyOnlineAmount(string $upToDate, ?int $supplierId): float
    {
        $startOfMonth = Carbon::parse($upToDate)->startOfMonth()->toDateString();

        $query = LedgerRegister::whereNotNull('posted_at')
            ->whereBetween('transaction_date', [$startOfMonth, $upToDate]);

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        return (float) $query->sum('online_amount');
    }

    public function getMainInvestmentTotal(string $date, ?int $supplierId, ?string $designation, array $employeeIds): float
    {
        return $this->getPowderExpiry($supplierId, $date)
            + $this->getLiquidExpiry($supplierId, $date)
            + $this->getClaimAmount($supplierId, $date)
            + $this->getStockAmount($supplierId, $date)
            + (float) $this->getSalesmanCreditData($date, $supplierId, $designation, $employeeIds)->sum('total_credit')
            + $this->getLedgerAmount($supplierId, $date);
    }

    public function getDailyBankSlips(string $date, ?int $supplierId): float
    {
        $query = DB::table('sales_settlement_bank_slips as bs')
            ->join('sales_settlements as ss', 'bs.sales_settlement_id', '=', 'ss.id')
            ->whereDate('ss.settlement_date', $date)
            ->whereNull('ss.deleted_at');

        if ($supplierId) {
            $query->where('ss.supplier_id', $supplierId);
        }

        return (float) $query->sum('bs.amount');
    }

    public function getDailyBankTransfers(string $date, ?int $supplierId): float
    {
        $query = DB::table('sales_settlement_bank_transfers as bt')
            ->join('sales_settlements as ss', 'bt.sales_settlement_id', '=', 'ss.id')
            ->whereDate('ss.settlement_date', $date)
            ->whereNull('ss.deleted_at');

        if ($supplierId) {
            $query->where('ss.supplier_id', $supplierId);
        }

        $bankTransfersAmount = (float) $query->sum('bt.amount');

        $recoveryQuery = DB::table('sales_settlement_recoveries as sr')
            ->join('sales_settlements as ss', 'sr.sales_settlement_id', '=', 'ss.id')
            ->whereDate('ss.settlement_date', $date)
            ->whereNull('ss.deleted_at')
            ->whereNull('sr.deleted_at')
            ->where('sr.payment_method', 'bank_transfer');

        if ($supplierId) {
            $recoveryQuery->where('ss.supplier_id', $supplierId);
        }

        $recoveryBankTransferAmount = (float) $recoveryQuery->sum('sr.amount');

        return $bankTransfersAmount + $recoveryBankTransferAmount;
    }

    public function getDailyChequePayments(string $date, ?int $supplierId): float
    {
        $query = DB::table('sales_settlement_cheques as sc')
            ->join('sales_settlements as ss', 'sc.sales_settlement_id', '=', 'ss.id')
            ->whereDate('ss.settlement_date', $date)
            ->whereNull('ss.deleted_at');

        if ($supplierId) {
            $query->where('ss.supplier_id', $supplierId);
        }

        return (float) $query->sum('sc.amount');
    }

    /**
     * @return array<string, float>|null
     */
    public function getOpeningBalanceValues(string $date, ?int $supplierId): ?array
    {
        $records = InvestmentOpeningBalance::where('date', $date)
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->pluck('amount', 'description');

        if ($records->isEmpty()) {
            return null;
        }

        return $records->mapWithKeys(fn ($amount, $desc) => [$desc => (float) $amount])->all();
    }

    /**
     * Returns the saved main investment for a given date if a snapshot exists,
     * preferring the month's own CURRENT_MONTH_MAIN_INVESTMENT and falling back
     * to a stored LAST_MONTH_MAIN_INVESTMENT. Null when no snapshot is saved.
     */
    public function getSavedMainInvestment(string $date, ?int $supplierId): ?float
    {
        $amounts = InvestmentOpeningBalance::where('date', $date)
            ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
            ->whereIn('description', ['CURRENT_MONTH_MAIN_INVESTMENT', 'LAST_MONTH_MAIN_INVESTMENT'])
            ->pluck('amount', 'description');

        $amount = $amounts['CURRENT_MONTH_MAIN_INVESTMENT'] ?? $amounts['LAST_MONTH_MAIN_INVESTMENT'] ?? null;

        return $amount !== null ? (float) $amount : null;
    }

    /**
     * What the business has tied up as of a date, one supplier or all: the same parts
     * as the report's "main investment" (expiry, claims, stock, market credit, supplier ledger).
     *
     * @return array{powder_expiry: float, liquid_expiry: float, claims: float, stock: float, credit: float, ledger: float, total: float}
     */
    public function mainInvestment(string $date, ?int $supplierId): array
    {
        $parts = [
            'powder_expiry' => $this->getPowderExpiry($supplierId, $date),
            'liquid_expiry' => $this->getLiquidExpiry($supplierId, $date),
            'claims' => $this->getClaimAmount($supplierId, $date),
            'stock' => $this->getStockAmount($supplierId, $date),
            'credit' => (float) $this->getSalesmanCreditData($date, $supplierId, 'Salesman', [])->sum('total_credit'),
            'ledger' => $this->getLedgerAmount($supplierId, $date),
        ];

        return $parts + ['total' => array_sum($parts)];
    }
}
