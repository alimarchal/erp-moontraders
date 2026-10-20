<?php

namespace App\Http\Controllers\Reports;

use App\Helpers\ReportPdf;
use App\Helpers\SvgChart;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Company-wise advance tax, added up per customer.
 *
 * The Advance Tax Sales Register shows the same 1161 amounts date by date; this report
 * shows them customer by customer, with the customer details printed beside each total.
 */
class AdvanceTaxCustomerSummaryController extends Controller implements HasMiddleware
{
    /**
     * Advance tax charged on sales, the same 2.5% the Advance Tax Sales Register uses.
     */
    private const ADVANCE_TAX_RATE = 0.025;

    /**
     * Benefit (account 1161) given to an NTN customer, as a share of the customer's sale. The rest of
     * the 2.5% advance tax is what is received from the customer.
     */
    private const BENEFIT_RATE = 0.02;

    /**
     * Per-customer advance tax entries live in one table for normal suppliers and in the
     * "income" table for suppliers flagged is_advance_tax_income. A supplier only ever
     * writes to one of them, so both are read and added together.
     *
     * @var array<int, string>
     */
    private const ENTRY_TABLES = ['sales_settlement_advance_taxes', 'sales_settlement_advance_tax_incomes'];

    public static function middleware(): array
    {
        return [
            new Middleware('can:report-audit-advance-tax-customer-summary'),
        ];
    }

    public function index(Request $request): View
    {
        $data = $this->reportData($request);

        return view('reports.advance-tax-customer-summary.index', $data + [
            'tiles' => $this->tiles($data),
            'charts' => $this->charts($data),
        ]);
    }

    /**
     * A4 PDF of the same report with the figure tiles and charts, portrait or landscape,
     * half inch margins, and the table header repeated on every page.
     */
    public function exportPdf(Request $request): Response
    {
        $data = $this->reportData($request);
        abort_unless($data['hasFilter'], 422, 'Select a company first.');

        $orientation = ReportPdf::orientation($request->query('orientation'));

        $pdf = ReportPdf::make('reports.advance-tax-customer-summary.pdf', $data + [
            'title' => 'Advance Tax Customer Summary',
            'subtitle' => [
                'Company: '.$data['selectedSupplier']->supplier_name,
                'Period: '.Carbon::parse($data['startDate'])->format('d M Y').' to '.Carbon::parse($data['endDate'])->format('d M Y'),
            ],
            'meta' => [
                'Customers' => $data['rows']->count(),
                'Printed by' => $request->user()->name,
                'On' => now()->format('d M Y, h:i A'),
            ],
            'tiles' => $this->tiles($data),
            'charts' => $this->charts($data),
        ], $orientation, 'Moon Traders ERP  |  Advance Tax Customer Summary  |  '.$data['selectedSupplier']->supplier_name);

        return $pdf->download('advance-tax-customer-summary-'.now()->format('Y-m-d').'.pdf');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array{label: string, value: string, note: string, color: string}>
     */
    private function tiles(array $data): array
    {
        if (! $data['hasFilter'] || $data['rows']->isEmpty()) {
            return [];
        }

        $customers = $data['rows']->count();

        return [
            ['label' => 'Sale', 'value' => 'Rs '.SvgChart::money($data['totalSale']), 'note' => number_format($data['totalSale']), 'color' => '#2563eb'],
            ['label' => 'Adv. tax (2.5%)', 'value' => 'Rs '.SvgChart::money($data['totalAdvanceTax']), 'note' => number_format($data['totalAdvanceTax']), 'color' => '#f97316'],
            ['label' => 'Benefits (NTN)', 'value' => 'Rs '.SvgChart::money($data['totalBenefits']), 'note' => 'account 1161, 2%', 'color' => '#7c3aed'],
            ['label' => 'Tax to pay', 'value' => 'Rs '.SvgChart::money($data['totalReceived']), 'note' => 'advance tax - benefits', 'color' => '#16a34a'],
            ['label' => 'Customers', 'value' => number_format($customers), 'note' => 'with an entry', 'color' => '#0891b2'],
        ];
    }

    /**
     * Three charts: where the advance tax went, the biggest customers, benefits against received.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private function charts(array $data): array
    {
        $customers = $data['rows'];

        if (! $data['hasFilter'] || $customers->isEmpty()) {
            return [];
        }

        $name = fn (array $row): string => (string) ($row['customer']?->business_name ?: $row['customer']?->customer_name);
        $bySale = $customers->sortByDesc('sale')->take(8)->values();
        $byTax = $customers->sortByDesc('advance_tax')->take(6)->values();

        return [
            [
                'title' => 'Where the advance tax went',
                'subtitle' => 'Benefit given to NTN customers and the tax still to pay',
                'accent' => '#7c3aed',
                'svg' => fn (int $w) => SvgChart::donut(
                    ['Tax to pay', 'Benefits (NTN)'],
                    [max($data['totalReceived'], 0), max($data['totalBenefits'], 0)],
                    ['#16a34a', '#7c3aed'],
                    $w, 200, SvgChart::money($data['totalAdvanceTax']), 'advance tax'
                ),
            ],
            [
                'title' => 'Biggest customers by sale',
                'subtitle' => 'Top 8 in the period',
                'accent' => '#2563eb',
                'svg' => fn (int $w) => SvgChart::bars(
                    $bySale->map($name)->all(),
                    $bySale->pluck('sale')->all(),
                    ['#2563eb', '#0891b2', '#16a34a', '#f59e0b', '#f97316', '#e11d48', '#7c3aed', '#64748b'],
                    $w, 200, null, 18
                ),
            ],
            [
                'title' => 'Benefits and received by customer',
                'subtitle' => 'Top 6 customers by advance tax',
                'accent' => '#f97316',
                'wide' => true,
                'svg' => fn (int $w) => SvgChart::columns(
                    $byTax->take($w < 500 ? 4 : 6)->map(fn (array $row) => explode(' ', $name($row))[0])->all(),
                    [
                        ['name' => 'Benefits (NTN)', 'color' => '#7c3aed', 'values' => $byTax->take($w < 500 ? 4 : 6)->pluck('benefits')->all()],
                        ['name' => 'Received from customer', 'color' => '#16a34a', 'values' => $byTax->take($w < 500 ? 4 : 6)->pluck('received')->all()],
                    ],
                    $w, 200
                ),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reportData(Request $request): array
    {
        $request->validate([
            'filter.supplier_id' => ['nullable', 'integer'],
            'filter.start_date' => ['nullable', 'date'],
            'filter.end_date' => ['nullable', 'date', 'after_or_equal:filter.start_date'],
        ]);

        $user = $request->user();
        $startDate = $request->input('filter.start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('filter.end_date', now()->endOfMonth()->format('Y-m-d'));
        $requestedSupplierId = $request->integer('filter.supplier_id') ?: null;

        if ($requestedSupplierId && ! $user->hasAllSupplierAccess() && $requestedSupplierId !== (int) $user->supplier_id) {
            abort(403, 'You do not have permission to view another company.');
        }

        $supplierId = $user->hasAllSupplierAccess()
            ? $requestedSupplierId
            : ($user->supplier_id ? (int) $user->supplier_id : null);

        $suppliers = Supplier::query()
            ->when(! $user->hasAllSupplierAccess(), fn ($query) => $user->supplier_id
                ? $query->whereKey($user->supplier_id)
                : $query->whereRaw('1 = 0'))
            ->orderBy('supplier_name')
            ->get(['id', 'supplier_name']);

        $selectedSupplier = $supplierId ? $suppliers->firstWhere('id', $supplierId) : null;
        $rows = collect();
        $totalSale = 0.0;

        if ($selectedSupplier) {
            ['rows' => $rows, 'total_sale' => $totalSale] = $this->customerRows($selectedSupplier->id, $startDate, $endDate);
        }

        $ntnSale = round((float) $rows->sum('sale'), 2);
        $ntnAdvanceTax = round((float) $rows->sum('advance_tax'), 2);
        $totalBenefits = round((float) $rows->sum('benefits'), 2);
        $ntnReceived = round((float) $rows->sum('received'), 2);
        $nonNtnSale = round($totalSale - $ntnSale, 2);
        $nonNtnAdvanceTax = round($nonNtnSale * self::ADVANCE_TAX_RATE, 2);

        return [
            'rows' => $rows,
            'suppliers' => $suppliers,
            'selectedSupplier' => $selectedSupplier,
            'selectedSupplierId' => $selectedSupplier?->id,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'hasFilter' => $selectedSupplier !== null,
            'totalSale' => $totalSale,
            'ntnSale' => $ntnSale,
            'ntnAdvanceTax' => $ntnAdvanceTax,
            'ntnReceived' => $ntnReceived,
            'nonNtnSale' => $nonNtnSale,
            'nonNtnAdvanceTax' => $nonNtnAdvanceTax,
            'totalAdvanceTax' => round($ntnAdvanceTax + $nonNtnAdvanceTax, 2),
            'totalBenefits' => $totalBenefits,
            // What is still to be paid: received from NTN customers plus the 2.5% on the non-NTN sale.
            'totalReceived' => round($ntnReceived + $nonNtnAdvanceTax, 2),
        ];
    }

    /**
     * One row per NTN customer, added up over the dates, and the settlements' total sale.
     *
     * The entries only record the customer and the benefit given (account 1161), which is 2% of what
     * the customer bought, so the customer's sale is worked back from it (benefit / 2%). The 2.5%
     * advance tax on that sale less the benefit is what is received from the customer. What is left
     * of the settlements' Total Sales after the NTN customers is the non-NTN sale.
     *
     * @return array{rows: Collection<int, array<string, mixed>>, total_sale: float}
     */
    private function customerRows(int $supplierId, string $startDate, string $endDate): array
    {
        $benefits = [];

        foreach (self::ENTRY_TABLES as $table) {
            $entries = DB::table("{$table} as entry")
                ->join('sales_settlements as settlement', 'settlement.id', '=', 'entry.sales_settlement_id')
                ->where('settlement.status', 'posted')
                ->whereNull('settlement.deleted_at')
                ->where('settlement.supplier_id', $supplierId)
                ->whereBetween('settlement.settlement_date', [$startDate, $endDate])
                ->groupBy('entry.customer_id')
                ->selectRaw('entry.customer_id, SUM(entry.tax_amount) as tax_amount')
                ->get();

            foreach ($entries as $entry) {
                $benefits[$entry->customer_id] = ($benefits[$entry->customer_id] ?? 0) + (float) $entry->tax_amount;
            }
        }

        $totalSale = (float) DB::table('sales_settlements as settlement')
            ->where('settlement.status', 'posted')
            ->whereNull('settlement.deleted_at')
            ->where('settlement.supplier_id', $supplierId)
            ->whereBetween('settlement.settlement_date', [$startDate, $endDate])
            ->sum('settlement.total_sales_amount');

        $customers = Customer::query()->whereIn('id', array_keys($benefits))->get()->keyBy('id');

        $rows = collect($benefits)
            ->map(function (float $benefit, int $customerId) use ($customers) {
                $sale = round($benefit / self::BENEFIT_RATE, 2);
                $advanceTax = round($sale * self::ADVANCE_TAX_RATE, 2);

                return [
                    'customer_id' => $customerId,
                    'customer' => $customers->get($customerId),
                    'sale' => $sale,
                    'advance_tax' => $advanceTax,
                    'benefits' => $benefit,
                    'received' => round($advanceTax - $benefit, 2),
                ];
            })
            ->sortBy(fn (array $row) => mb_strtolower((string) $row['customer']?->customer_name))
            ->values();

        return ['rows' => $rows, 'total_sale' => $totalSale];
    }
}
