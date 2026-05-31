<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProfitCategoryDetailRequest;
use App\Http\Requests\UpdateProfitCategoryDetailRequest;
use App\Models\ProfitCategory;
use App\Models\ProfitCategoryDetail;
use App\Models\Supplier;
use App\Services\ProfitCategoryDetailService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;

class ProfitAfterCategoryReportController extends Controller implements HasMiddleware
{
    public function __construct(private ProfitCategoryDetailService $profitCategoryDetailService) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:report-audit-profit-after-category', only: ['index']),
            new Middleware('can:profit-after-category-create', only: ['store']),
            new Middleware('can:profit-after-category-edit', only: ['update']),
            new Middleware('can:profit-after-category-post', only: ['post']),
            new Middleware('can:profit-after-category-delete', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 50);
        $perPage = in_array($perPage, [10, 25, 50, 100, 250, 'all']) ? $perPage : 50;

        $defaultSupplier = Supplier::where('short_name', 'Nestle')->first();
        $supplierId = $request->input('supplier_id', $defaultSupplier?->id);
        $dateFrom = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', now()->endOfMonth()->toDateString());
        $profitCategoryId = $request->input('profit_category_id');
        $postedStatus = $request->input('posted_status');

        $suppliers = Supplier::where('disabled', false)->orderBy('supplier_name')->get();
        $categoryOptions = ProfitCategory::query()
            ->where('is_active', true)
            ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
            ->orderBy('name')
            ->get();

        $openingBalance = 0;
        $isCurrentMonth = $dateFrom && Carbon::parse($dateFrom)->isSameMonth(now());

        if ($dateFrom && ! $isCurrentMonth) {
            $openingQuery = ProfitCategoryDetail::query();
            if ($supplierId) {
                $openingQuery->where('supplier_id', $supplierId);
            }
            if ($profitCategoryId) {
                $openingQuery->where('profit_category_id', $profitCategoryId);
            }
            $openingQuery->whereDate('transaction_date', '<', $dateFrom);
            $openingBalance = (float) $openingQuery->sum('amount');
        }

        $query = ProfitCategoryDetail::with(['supplier', 'profitCategory'])
            ->orderBy('transaction_date')
            ->orderBy('id');

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }
        if ($profitCategoryId) {
            $query->where('profit_category_id', $profitCategoryId);
        }
        if ($postedStatus === 'posted') {
            $query->whereNotNull('posted_at');
        } elseif ($postedStatus === 'unposted') {
            $query->whereNull('posted_at');
        }
        if ($dateFrom) {
            $query->whereDate('transaction_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('transaction_date', '<=', $dateTo);
        }

        if ($perPage === 'all') {
            $profitCategoryDetails = $query->get();
            $profitCategoryDetails = new LengthAwarePaginator(
                $profitCategoryDetails,
                $profitCategoryDetails->count(),
                $profitCategoryDetails->count() ?: 1,
                1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            $profitCategoryDetails = $query->paginate((int) $perPage)->withQueryString();
        }

        $totalAmount = (clone $query)->sum('amount');
        $closingBalance = $openingBalance + $totalAmount;

        $categoryTotals = [];
        foreach ($categoryOptions as $categoryOption) {
            $catQuery = ProfitCategoryDetail::query();
            if ($supplierId) {
                $catQuery->where('supplier_id', $supplierId);
            }
            if ($dateFrom) {
                $catQuery->whereDate('transaction_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $catQuery->whereDate('transaction_date', '<=', $dateTo);
            }
            $categoryTotals[$categoryOption->id] = (float) $catQuery
                ->where('profit_category_id', $categoryOption->id)
                ->sum('amount');
        }

        $selectedSupplier = $supplierId ? Supplier::find($supplierId) : null;

        return view('reports.profit-after-category.index', compact(
            'profitCategoryDetails',
            'suppliers',
            'supplierId',
            'selectedSupplier',
            'categoryOptions',
            'profitCategoryId',
            'postedStatus',
            'dateFrom',
            'dateTo',
            'openingBalance',
            'closingBalance',
            'totalAmount',
            'categoryTotals',
            'perPage',
        ));
    }

    public function store(StoreProfitCategoryDetailRequest $request)
    {
        $result = $this->profitCategoryDetailService->createProfitCategoryDetail($request->validated());

        if ($result['success']) {
            return redirect()->back()->with('success', $result['message']);
        }

        return redirect()->back()->withInput()->with('error', $result['message']);
    }

    public function update(UpdateProfitCategoryDetailRequest $request, ProfitCategoryDetail $profitCategoryDetail)
    {
        if ($profitCategoryDetail->isPosted()) {
            return redirect()->back()->with('error', 'Posted profit category entries cannot be edited.');
        }

        $result = $this->profitCategoryDetailService->updateProfitCategoryDetail($profitCategoryDetail, $request->validated());

        if ($result['success']) {
            return redirect()->back()->with('success', $result['message']);
        }

        return redirect()->back()->withInput()->with('error', $result['message']);
    }

    public function destroy(ProfitCategoryDetail $profitCategoryDetail)
    {
        if ($profitCategoryDetail->isPosted()) {
            return redirect()->back()->with('error', 'Posted profit category entries cannot be deleted.');
        }

        try {
            $profitCategoryDetail->delete();

            return redirect()->back()->with('success', 'Profit category entry deleted successfully.');
        } catch (\Throwable $e) {
            Log::error('Profit after category report destroy error: '.$e->getMessage());

            return redirect()->back()->with('error', 'Failed to delete profit category entry. Please try again.');
        }
    }

    public function post(ProfitCategoryDetail $profitCategoryDetail)
    {
        if ($profitCategoryDetail->isPosted()) {
            return redirect()->back()->with('error', 'Profit category entry is already posted.');
        }

        $result = $this->profitCategoryDetailService->postProfitCategoryDetail($profitCategoryDetail);

        if ($result['success']) {
            return redirect()->back()->with('success', $result['message']);
        }

        return redirect()->back()->with('error', $result['message']);
    }
}
