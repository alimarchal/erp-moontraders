<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSchemeReceivedRequest;
use App\Http\Requests\UpdateSchemeReceivedRequest;
use App\Models\SchemeReceived;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class SchemeReceivedReportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-sales-scheme-received', only: ['index']),
            new Middleware('can:scheme-received-create', only: ['store']),
            new Middleware('can:scheme-received-edit', only: ['update']),
            new Middleware('can:scheme-received-delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $perPage = $request->input('per_page', 50);
        $perPage = in_array($perPage, [10, 25, 50, 100, 250, 'all']) ? $perPage : 50;

        $defaultSupplier = Supplier::where('short_name', 'Nestle')->first();
        $supplierId = $request->input('supplier_id', $defaultSupplier?->id);

        $dateFrom = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', now()->endOfMonth()->toDateString());
        $category = $request->input('category');
        $activeStatus = $request->input('active_status');

        $suppliers = Supplier::where('disabled', false)->orderBy('supplier_name')->get();
        $categoryOptions = SchemeReceived::categoryOptions();

        $openingBalance = 0;
        if ($dateFrom) {
            $openingQuery = SchemeReceived::query();
            if ($supplierId) {
                $openingQuery->where('supplier_id', $supplierId);
            }
            if ($category) {
                $openingQuery->where('category', $category);
            }
            $openingQuery->whereDate('transaction_date', '<', $dateFrom);
            $openingBalance = (float) $openingQuery->sum('amount');
        }

        $query = SchemeReceived::with('supplier')
            ->orderBy('transaction_date')
            ->orderBy('id');

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }
        if ($category) {
            $query->where('category', $category);
        }
        if ($activeStatus === 'active') {
            $query->where('is_active', true);
        } elseif ($activeStatus === 'inactive') {
            $query->where('is_active', false);
        }
        if ($dateFrom) {
            $query->whereDate('transaction_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('transaction_date', '<=', $dateTo);
        }

        if ($perPage === 'all') {
            $records = $query->get();
            $records = new LengthAwarePaginator(
                $records,
                $records->count(),
                $records->count() ?: 1,
                1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            $records = $query->paginate((int) $perPage)->withQueryString();
        }

        $totalAmount = (clone $query)->sum('amount');
        $closingBalance = $openingBalance + $totalAmount;

        $categoryTotals = [];
        foreach (array_keys($categoryOptions) as $cat) {
            $catQuery = SchemeReceived::query();
            if ($supplierId) {
                $catQuery->where('supplier_id', $supplierId);
            }
            if ($dateFrom) {
                $catQuery->whereDate('transaction_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $catQuery->whereDate('transaction_date', '<=', $dateTo);
            }
            $categoryTotals[$cat] = (float) $catQuery->where('category', $cat)->sum('amount');
        }

        $selectedSupplier = $supplierId ? Supplier::find($supplierId) : null;

        return view('reports.scheme-received.index', compact(
            'records',
            'suppliers',
            'supplierId',
            'selectedSupplier',
            'categoryOptions',
            'category',
            'activeStatus',
            'dateFrom',
            'dateTo',
            'openingBalance',
            'closingBalance',
            'totalAmount',
            'categoryTotals',
            'perPage',
        ));
    }

    public function store(StoreSchemeReceivedRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = isset($data['is_active']) ? (bool) $data['is_active'] : true;

        SchemeReceived::create($data);

        return redirect()->back()->with('success', 'Scheme received entry added successfully.');
    }

    public function update(UpdateSchemeReceivedRequest $request, SchemeReceived $schemeReceived): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = isset($data['is_active']) ? (bool) $data['is_active'] : true;

        $schemeReceived->update($data);

        return redirect()->back()->with('success', 'Scheme received entry updated successfully.');
    }

    public function destroy(SchemeReceived $schemeReceived): RedirectResponse
    {
        try {
            $schemeReceived->delete();

            return redirect()->back()->with('success', 'Entry deleted successfully.');
        } catch (\Throwable $e) {
            Log::error('SchemeReceived destroy error: '.$e->getMessage());

            return redirect()->back()->with('error', 'Failed to delete entry. Please try again.');
        }
    }
}
