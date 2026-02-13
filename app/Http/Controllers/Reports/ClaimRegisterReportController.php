<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\ClaimRegister;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ClaimRegisterReportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-view-audit'),
        ];
    }

    public function index(Request $request)
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to', now()->format('Y-m-d'));
        $supplierId = $request->input('supplier_id');
        $claimMonth = $request->input('claim_month');
        $transactionType = $request->input('transaction_type');

        $suppliers = Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']);
        $transactionTypeOptions = ClaimRegister::transactionTypeOptions();

        $openingBalance = 0;
        if ($dateFrom) {
            $openingQuery = ClaimRegister::query();

            if ($supplierId) {
                $openingQuery->where('supplier_id', $supplierId);
            }

            $openingQuery->whereDate('transaction_date', '<', $dateFrom);

            $openingRecords = $openingQuery->get();
            foreach ($openingRecords as $record) {
                $openingBalance += (float) $record->debit - (float) $record->credit;
            }
        }

        $query = ClaimRegister::with('supplier')
            ->orderBy('transaction_date')
            ->orderBy('id');

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        if ($claimMonth) {
            $query->where('claim_month', 'like', "%{$claimMonth}%");
        }

        if ($transactionType) {
            $query->where('transaction_type', $transactionType);
        }

        if ($dateFrom) {
            $query->whereDate('transaction_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('transaction_date', '<=', $dateTo);
        }

        $claims = $query->get();

        $totals = [
            'debit' => $claims->sum('debit'),
            'credit' => $claims->sum('credit'),
        ];

        $totals['net_balance'] = $totals['debit'] - $totals['credit'];
        $closingBalance = $openingBalance + $totals['net_balance'];

        return view('reports.claim-register.index', compact(
            'claims',
            'suppliers',
            'transactionTypeOptions',
            'supplierId',
            'claimMonth',
            'transactionType',
            'dateFrom',
            'dateTo',
            'openingBalance',
            'closingBalance',
            'totals'
        ));
    }
}
