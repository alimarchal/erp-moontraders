<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\SalesSettlement;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashDetailController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->input('date', now()->format('Y-m-d'));
        $supplierId = $request->input('supplier_id');

        $suppliers = Supplier::orderBy('supplier_name')->get();

        // Base Query for Settlements on the selected date
        $query = SalesSettlement::with(['employee', 'cashDenominations', 'bankSlips'])
            ->whereDate('settlement_date', $date);

        if ($supplierId) {
            $query->whereHas('employee', function ($q) use ($supplierId) {
                $q->where('supplier_id', $supplierId);
            });
        }

        $settlements = $query->get();

        // 1. Salesman Data
        $salesmanData = $settlements->map(function ($settlement) {
            $cashDenom = $settlement->cashDenominations->first();
            // Use physical cash if recorded (total_amount > 0), else fallback to cash_collected
            // Logic per user/plan: "Physical Cash" logic
            $amount = ($cashDenom && $cashDenom->total_amount > 0)
                ? $cashDenom->total_amount
                : $settlement->cash_collected;

            return (object) [
                'salesman_name' => $settlement->employee->name,
                'amount' => $amount
            ];
        })->sortByDesc('amount');

        // 2. Cash Denominations Aggregation
        $denominations = [
            '5000' => 0,
            '1000' => 0,
            '500' => 0,
            '100' => 0,
            '50' => 0,
            '20' => 0,
            '10' => 0,
            'coins' => 0
        ];

        foreach ($settlements as $settlement) {
            $denom = $settlement->cashDenominations->first();
            if ($denom) {
                $denominations['5000'] += $denom->denom_5000;
                $denominations['1000'] += $denom->denom_1000;
                $denominations['500'] += $denom->denom_500;
                $denominations['100'] += $denom->denom_100;
                $denominations['50'] += $denom->denom_50;
                $denominations['20'] += $denom->denom_20;
                $denominations['10'] += $denom->denom_10;
                $denominations['coins'] += $denom->denom_coins;
            }
        }

        // 3. Bank Slips
        $bankSlips = $settlements->flatMap(function ($settlement) {
            return $settlement->bankSlips->map(function ($slip) use ($settlement) {
                return (object) [
                    'salesman_name' => $settlement->employee->name,
                    'bank_name' => $slip->bankAccount->account_name ?? 'Unknown',
                    'amount' => $slip->amount
                ];
            });
        });

        return view('reports.cash-detail', compact(
            'date',
            'supplierId',
            'suppliers',
            'salesmanData',
            'denominations',
            'bankSlips'
        ));
    }
}
