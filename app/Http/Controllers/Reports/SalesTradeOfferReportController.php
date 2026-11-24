<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Services\SalesTradeOfferService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * Daily Sales & Trade Offer Dashboard: every company side by side with its sale, trade offer and
 * promotion received and passed, percentage and balance for the period (1st of the month to today by
 * default). Users without the all-companies permission only see their own company's column.
 */
class SalesTradeOfferReportController extends Controller implements HasMiddleware
{
    public const ALL_COMPANIES = 'all';

    public function __construct(private SalesTradeOfferService $tradeOffer) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:report-sales-trade-offer-dashboard'),
        ];
    }

    public function index(Request $request): View
    {
        $request->validate([
            'filter.start_date' => ['nullable', 'date'],
            'filter.end_date' => ['nullable', 'date', 'after_or_equal:filter.start_date'],
            'filter.supplier_ids' => ['nullable', 'array'],
            'filter.supplier_ids.*' => ['regex:/^(\d+|'.self::ALL_COMPANIES.')$/'],
            'filter.sort' => ['nullable', 'in:'.SalesTradeOfferService::SORT_BY_NAME.','.SalesTradeOfferService::SORT_BY_SALES],
        ]);

        $user = $request->user();
        $canChooseCompany = $user->can('report-sales-trade-offer-dashboard-all-companies') || $user->isTicketAdmin();
        $ownSupplierId = $user->supplier_id ? (int) $user->supplier_id : null;

        $startDate = Carbon::parse($request->input('filter.start_date', now()->startOfMonth()->toDateString()))->toDateString();
        $endDate = Carbon::parse($request->input('filter.end_date', now()->toDateString()))->toDateString();
        $sort = (string) $request->input('filter.sort', SalesTradeOfferService::SORT_BY_NAME);

        $requested = array_map('strval', (array) $request->input('filter.supplier_ids', []));
        $allCompanies = $requested === [] || in_array(self::ALL_COMPANIES, $requested, true);
        $requestedIds = $allCompanies ? [] : array_values(array_unique(array_map('intval', $requested)));

        if ($canChooseCompany) {
            $supplierIds = $allCompanies ? null : $requestedIds;
        } else {
            abort_if(array_diff($requestedIds, [$ownSupplierId]) !== [], 403, 'You can only see the report of your own company.');
            $supplierIds = $ownSupplierId ? [$ownSupplierId] : [];
        }

        $suppliers = Supplier::query()
            ->where('disabled', false)
            ->when(! $canChooseCompany, fn ($query) => $query->where('id', $ownSupplierId ?? 0))
            ->orderBy('supplier_name')
            ->get(['id', 'supplier_name', 'short_name']);

        $report = $this->tradeOffer->build($startDate, $endDate, $supplierIds, $sort);

        return view('reports.sales-trade-offer.index', [
            'report' => $report,
            'rows' => SalesTradeOfferService::ROWS,
            'suppliers' => $suppliers,
            'allCompanies' => $canChooseCompany && $allCompanies,
            'selectedSupplierIds' => $canChooseCompany ? $requestedIds : array_filter([$ownSupplierId]),
            'canChooseCompany' => $canChooseCompany,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'sort' => $sort,
        ]);
    }
}
