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
        $status = $request->input('status');
        $claimMonth = $request->input('claim_month');
        $transactionType = $request->input('transaction_type');

        $suppliers = Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']);
        $statusOptions = ClaimRegister::statusOptions();
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
                if ($record->transaction_type === 'claim') {
                    $openingBalance += (float) $record->amount;
                } else {
                    $openingBalance -= (float) $record->amount;
                }
            }
        }

        $query = ClaimRegister::with('supplier')
            ->orderBy('transaction_date')
            ->orderBy('id');

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        if ($status) {
            $query->where('status', $status);
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
            'claim_amount' => 0,
            'recovery_amount' => 0,
        ];

        foreach ($claims as $claim) {
            if ($claim->transaction_type === 'claim') {
                $totals['claim_amount'] += (float) $claim->amount;
            } else {
                $totals['recovery_amount'] += (float) $claim->amount;
            }
        }

        $totals['net_balance'] = $totals['claim_amount'] - $totals['recovery_amount'];
        $closingBalance = $openingBalance + $totals['net_balance'];

        return view('reports.claim-register.index', compact(
            'claims',
            'suppliers',
            'statusOptions',
            'transactionTypeOptions',
            'supplierId',
            'status',
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
