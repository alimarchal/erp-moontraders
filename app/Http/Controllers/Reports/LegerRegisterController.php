<?php

namespace App\Http\Controllers\Reports;

use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLegerRegisterRequest;
use App\Http\Requests\UpdateLegerRegisterRequest;
use App\Models\LegerRegister;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LegerRegisterController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-audit-leger-register', only: ['index']),
            new Middleware('can:report-audit-leger-register-manage', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 50);
        $perPage = \in_array($perPage, [10, 25, 50, 100, 250, 'all']) ? $perPage : 50;

        // Default supplier: Nestlé
        $defaultSupplier = Supplier::where('short_name', 'Nestle')->first();
        $supplierId = $request->input('filter.supplier_id', $defaultSupplier?->id);

        // Date range defaults: current month
        $dateFrom = $request->input('filter.date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->input('filter.date_to', now()->endOfMonth()->toDateString());

        $query = LegerRegister::query()
            ->with('supplier')
            ->when($supplierId, fn ($q) => $q->forSupplier($supplierId))
            ->dateRange($dateFrom, $dateTo);

        if ($request->filled('filter.document_type')) {
            $types = (array) $request->input('filter.document_type');
            $query->whereIn('document_type', $types);
        }

        if ($request->filled('filter.document_number')) {
            $query->where('document_number', 'like', '%'.$request->input('filter.document_number').'%');
        }

        if ($request->filled('filter.sap_code')) {
            $query->where('sap_code', 'like', '%'.$request->input('filter.sap_code').'%');
        }

        if ($request->filled('filter.balance_min')) {
            $query->where('balance', '>=', $request->input('filter.balance_min'));
        }

        if ($request->filled('filter.balance_max')) {
            $query->where('balance', '<=', $request->input('filter.balance_max'));
        }

        $sortDirection = $request->input('sort', 'asc');
        $sortDirection = in_array($sortDirection, ['asc', 'desc']) ? $sortDirection : 'asc';

        $query->orderBy('transaction_date', $sortDirection)->orderBy('id', $sortDirection);

        // Calculate running balance considering entries before the current page
        $allFilteredQuery = LegerRegister::query()
            ->when($supplierId, fn ($q) => $q->forSupplier($supplierId))
            ->dateRange($dateFrom, $dateTo);

        if ($request->filled('filter.document_type')) {
            $allFilteredQuery->whereIn('document_type', (array) $request->input('filter.document_type'));
        }
        if ($request->filled('filter.document_number')) {
            $allFilteredQuery->where('document_number', 'like', '%'.$request->input('filter.document_number').'%');
        }
        if ($request->filled('filter.sap_code')) {
            $allFilteredQuery->where('sap_code', 'like', '%'.$request->input('filter.sap_code').'%');
        }

        // Column totals (for all filtered entries, not just current page)
        $totals = (clone $allFilteredQuery)->selectRaw('
            COALESCE(SUM(online_amount), 0) as total_online,
            COALESCE(SUM(invoice_amount), 0) as total_invoice,
            COALESCE(SUM(expenses_amount), 0) as total_expenses,
            COALESCE(SUM(za_point_five_percent_amount), 0) as total_za,
            COALESCE(SUM(claim_adjust_amount), 0) as total_claim_adjust,
            COALESCE(SUM(advance_tax_amount), 0) as total_advance_tax,
            COUNT(*) as total_entries
        ')->first();

        if ($perPage === 'all') {
            $allEntries = $query->get();
            $entries = new \Illuminate\Pagination\LengthAwarePaginator(
                $allEntries,
                $allEntries->count(),
                $allEntries->count() ?: 1,
                1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            $entries = $query->paginate((int) $perPage)->withQueryString();
        }

        // Opening balance (sum of all entries for this supplier BEFORE dateFrom)
        $openingBalance = 0;
        if ($supplierId && $dateFrom) {
            $openingBalance = (float) LegerRegister::where('supplier_id', $supplierId)
                ->where('transaction_date', '<', $dateFrom)
                ->selectRaw('COALESCE(SUM(online_amount - invoice_amount - expenses_amount + za_point_five_percent_amount + claim_adjust_amount), 0) as balance')
                ->value('balance');
        }

        // Calculate running balance for displayed entries
        // Account for entries before current page within the filtered range
        $balanceBeforePage = $openingBalance;
        if ($entries->currentPage() > 1 && $perPage !== 'all') {
            $entriesBeforePage = ($entries->currentPage() - 1) * (int) $perPage;
            $beforePageBalance = (clone $allFilteredQuery)
                ->orderBy('transaction_date', $sortDirection)
                ->orderBy('id', $sortDirection)
                ->limit($entriesBeforePage)
                ->selectRaw('COALESCE(SUM(online_amount - invoice_amount - expenses_amount + za_point_five_percent_amount + claim_adjust_amount), 0) as balance')
                ->value('balance');

            $balanceBeforePage = $openingBalance + (float) $beforePageBalance;
        }

        $runningBalance = $balanceBeforePage;
        $entries->getCollection()->transform(function ($entry) use (&$runningBalance) {
            $runningBalance += (float) $entry->online_amount
                - (float) $entry->invoice_amount
                - (float) $entry->expenses_amount
                + (float) $entry->za_point_five_percent_amount
                + (float) $entry->claim_adjust_amount;
            $entry->running_balance = round($runningBalance, 2);

            return $entry;
        });

        $currentBalance = $openingBalance
            + (float) ($totals->total_online ?? 0)
            - (float) ($totals->total_invoice ?? 0)
            - (float) ($totals->total_expenses ?? 0)
            + (float) ($totals->total_za ?? 0)
            + (float) ($totals->total_claim_adjust ?? 0);

        $suppliers = Supplier::where('disabled', false)->orderBy('supplier_name')->get();
        $selectedSupplier = $supplierId ? Supplier::find($supplierId) : null;

        return view('reports.leger-register.index', [
            'entries' => $entries,
            'totals' => $totals,
            'openingBalance' => $openingBalance,
            'currentBalance' => $currentBalance,
            'suppliers' => $suppliers,
            'selectedSupplier' => $selectedSupplier,
            'documentTypes' => DocumentType::cases(),
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }

    public function store(StoreLegerRegisterRequest $request)
    {
        try {
            DB::transaction(function () use ($request) {
                LegerRegister::create($request->validated());
                LegerRegister::recalculateBalances($request->validated()['supplier_id']);
            });

            return redirect()->back()->with('success', 'Ledger entry added successfully.');
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('LegerRegister store error: '.$e->getMessage());

            return redirect()->back()->withInput()->with('error', 'Failed to add ledger entry. Please try again.');
        }
    }

    public function update(UpdateLegerRegisterRequest $request, LegerRegister $legerRegister)
    {
        try {
            DB::transaction(function () use ($request, $legerRegister) {
                $legerRegister->update($request->validated());
                LegerRegister::recalculateBalances($legerRegister->supplier_id);
            });

            return redirect()->back()->with('success', 'Ledger entry updated successfully.');
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('LegerRegister update error: '.$e->getMessage());

            return redirect()->back()->withInput()->with('error', 'Failed to update ledger entry. Please try again.');
        }
    }

    public function destroy(LegerRegister $legerRegister)
    {
        try {
            $supplierId = $legerRegister->supplier_id;

            DB::transaction(function () use ($legerRegister, $supplierId) {
                $legerRegister->delete();
                LegerRegister::recalculateBalances($supplierId);
            });

            return redirect()->back()->with('success', 'Ledger entry deleted successfully.');
        } catch (\Throwable $e) {
            Log::error('LegerRegister destroy error: '.$e->getMessage());

            return redirect()->back()->with('error', 'Failed to delete ledger entry. Please try again.');
        }
    }
}
