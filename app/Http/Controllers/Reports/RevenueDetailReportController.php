<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRevenueDetailRequest;
use App\Http\Requests\UpdateRevenueDetailRequest;
use App\Models\RevenueCategory;
use App\Models\RevenueDetail;
use App\Models\Supplier;
use App\Services\RevenueDetailService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;

class RevenueDetailReportController extends Controller implements HasMiddleware
{
    public function __construct(private RevenueDetailService $revenueService) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:report-audit-revenue-detail', only: ['index']),
            new Middleware('can:revenue-detail-create', only: ['store']),
            new Middleware('can:revenue-detail-edit', only: ['update']),
            new Middleware('can:revenue-detail-post', only: ['post']),
            new Middleware('can:revenue-detail-delete', only: ['destroy']),
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
        $revenueCategoryId = $request->input('revenue_category_id');
        $postedStatus = $request->input('posted_status');

        $suppliers = Supplier::where('disabled', false)->orderBy('supplier_name')->get();
        $categoryOptions = RevenueCategory::query()
            ->where('is_active', true)
            ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
            ->orderBy('name')
            ->get();

        $openingBalance = 0;
        $isCurrentMonth = $dateFrom && Carbon::parse($dateFrom)->isSameMonth(now());

        if ($dateFrom && ! $isCurrentMonth) {
            $openingQuery = RevenueDetail::query();
            if ($supplierId) {
                $openingQuery->where('supplier_id', $supplierId);
            }
            if ($revenueCategoryId) {
                $openingQuery->where('revenue_category_id', $revenueCategoryId);
            }
            $openingQuery->whereDate('transaction_date', '<', $dateFrom);
            $openingBalance = (float) $openingQuery->sum('amount');
        }

        $query = RevenueDetail::with(['supplier', 'revenueCategory'])
            ->orderBy('transaction_date')
            ->orderBy('id');

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }
        if ($revenueCategoryId) {
            $query->where('revenue_category_id', $revenueCategoryId);
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
            $revenues = $query->get();
            $revenues = new LengthAwarePaginator(
                $revenues,
                $revenues->count(),
                $revenues->count() ?: 1,
                1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            $revenues = $query->paginate((int) $perPage)->withQueryString();
        }

        $totalAmount = (clone $query)->sum('amount');
        $closingBalance = $openingBalance + $totalAmount;

        $categoryTotals = [];
        foreach ($categoryOptions as $categoryOption) {
            $catQuery = RevenueDetail::query();
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
                ->where('revenue_category_id', $categoryOption->id)
                ->sum('amount');
        }

        $selectedSupplier = $supplierId ? Supplier::find($supplierId) : null;

        return view('reports.revenue-detail.index', compact(
            'revenues',
            'suppliers',
            'supplierId',
            'selectedSupplier',
            'categoryOptions',
            'revenueCategoryId',
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

    public function store(StoreRevenueDetailRequest $request)
    {
        $data = $this->prepareData($request->validated());
        $result = $this->revenueService->createRevenue($data);

        if ($result['success']) {
            return redirect()->back()->with('success', $result['message']);
        }

        return redirect()->back()->withInput()->with('error', $result['message']);
    }

    public function update(UpdateRevenueDetailRequest $request, RevenueDetail $revenueDetail)
    {
        if ($revenueDetail->isPosted()) {
            return redirect()->back()->with('error', 'Posted revenues cannot be edited.');
        }

        $data = $this->prepareData($request->validated());
        $result = $this->revenueService->updateRevenue($revenueDetail, $data);

        if ($result['success']) {
            return redirect()->back()->with('success', $result['message']);
        }

        return redirect()->back()->withInput()->with('error', $result['message']);
    }

    public function destroy(RevenueDetail $revenueDetail)
    {
        if ($revenueDetail->isPosted()) {
            return redirect()->back()->with('error', 'Posted revenues cannot be deleted.');
        }

        try {
            $revenueDetail->delete();

            return redirect()->back()->with('success', 'Revenue deleted successfully.');
        } catch (\Throwable $e) {
            Log::error('RevenueDetail report destroy error: '.$e->getMessage());

            return redirect()->back()->with('error', 'Failed to delete revenue. Please try again.');
        }
    }

    public function post(RevenueDetail $revenueDetail)
    {
        if ($revenueDetail->isPosted()) {
            return redirect()->back()->with('error', 'Revenue is already posted.');
        }

        $result = $this->revenueService->postRevenue($revenueDetail);

        if ($result['success']) {
            return redirect()->back()->with('success', $result['message']);
        }

        return redirect()->back()->with('error', $result['message']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareData(array $data): array
    {
        $category = RevenueCategory::find($data['revenue_category_id']);

        $data['supplier_id'] = $data['supplier_id'] ?? $category?->supplier_id;

        return $data;
    }
}
