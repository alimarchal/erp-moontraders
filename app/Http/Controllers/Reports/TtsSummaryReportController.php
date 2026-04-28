<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\SchemeReceived;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TtsSummaryReportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-sales-tts-summary', only: ['index']),
        ];
    }

    public function index(Request $request): View
    {
        if (! $request->filled('filter.start_date')) {
            $startDate = Carbon::now()->startOfMonth();
            $endDate = Carbon::now()->endOfMonth();
        } else {
            $startDate = Carbon::parse($request->input('filter.start_date'));
            $endDate = Carbon::parse($request->input('filter.end_date', Carbon::now()->endOfMonth()->format('Y-m-d')));
        }

        $defaultSupplier = Supplier::where('short_name', 'Nestle')->first();
        $supplierId = (int) $request->input('filter.supplier_id', $defaultSupplier?->id);

        $suppliers = Supplier::where('disabled', false)
            ->orderBy('supplier_name')
            ->get(['id', 'supplier_name', 'short_name']);

        $ttsReceived = (float) SchemeReceived::query()
            ->where('supplier_id', $supplierId)
            ->where('category', 'tts_received')
            ->where('is_active', true)
            ->whereBetween('transaction_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->sum('amount');

        $promoReceived = (float) SchemeReceived::query()
            ->where('supplier_id', $supplierId)
            ->where('category', 'promo_received')
            ->where('is_active', true)
            ->whereBetween('transaction_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->sum('amount');

        $totalReceived = $ttsReceived + $promoReceived;

        $expenseBaseQuery = DB::table('sales_settlement_expenses')
            ->join('sales_settlements', 'sales_settlement_expenses.sales_settlement_id', '=', 'sales_settlements.id')
            ->join('chart_of_accounts', 'sales_settlement_expenses.expense_account_id', '=', 'chart_of_accounts.id')
            ->whereBetween('sales_settlements.settlement_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->where('sales_settlements.status', 'posted')
            ->where('sales_settlements.supplier_id', $supplierId);

        $ttsPassed = (float) (clone $expenseBaseQuery)
            ->where('chart_of_accounts.account_code', '5292')
            ->sum('sales_settlement_expenses.amount');

        $promoPassed = (float) (clone $expenseBaseQuery)
            ->where('chart_of_accounts.account_code', '5287')
            ->sum('sales_settlement_expenses.amount');

        $percentagePassed = (float) (clone $expenseBaseQuery)
            ->where('chart_of_accounts.account_code', '5223')
            ->sum('sales_settlement_expenses.amount');

        $totalSchemedPassed = $ttsPassed + $promoPassed;
        $totalBalance = $totalReceived - $totalSchemedPassed - $percentagePassed;

        $selectedSupplier = $suppliers->firstWhere('id', $supplierId);

        return view('reports.tts-summary.index', compact(
            'suppliers',
            'supplierId',
            'selectedSupplier',
            'startDate',
            'endDate',
            'ttsReceived',
            'promoReceived',
            'totalReceived',
            'ttsPassed',
            'promoPassed',
            'totalSchemedPassed',
            'percentagePassed',
            'totalBalance'
        ));
    }
}
