<?php

namespace App\Http\Controllers\Reports;

use App\Exports\CompanyTargetSummaryExport;
use App\Helpers\ReportPdf;
use App\Helpers\SvgChart;
use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Services\CompanyTargetService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class CompanyTargetSummaryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-audit-company-target-summary'),
        ];
    }

    public function index(Request $request, CompanyTargetService $service)
    {
        $data = $this->reportData($request, $service);

        return view('reports.company-target-summary.index', $data + [
            'suppliers' => $this->suppliersForUser(),
            'tiles' => $this->tiles($data['summary']),
            'charts' => $this->charts($data['summary']),
        ]);
    }

    public function exportExcel(Request $request, CompanyTargetService $service): BinaryFileResponse
    {
        $data = $this->reportData($request, $service);

        return Excel::download(
            new CompanyTargetSummaryExport($data),
            'company-target-summary-'.now()->format('Y-m-d').'.xlsx'
        );
    }

    /**
     * A4 PDF with the figure tiles, charts and table; portrait or landscape (?orientation=).
     */
    public function exportPdf(Request $request, CompanyTargetService $service): Response
    {
        $data = $this->reportData($request, $service);
        abort_unless($data['summary'], 422, 'Select a company first.');

        $orientation = ReportPdf::orientation($request->query('orientation'));
        $generatedAt = now();

        $pdf = ReportPdf::make('reports.company-target-summary.pdf', $data + [
            'title' => 'Company Target Summary',
            'subtitle' => [
                'Distributor: '.$data['supplier']->supplier_name,
                'Period: '.$data['from']->format('d M Y').' to '.$data['to']->format('d M Y'),
            ],
            'meta' => [
                'Days left' => $data['summary']['days_left'],
                'Printed by' => $request->user()->name,
                'On' => $generatedAt->format('d M Y, h:i A'),
            ],
            'tiles' => $this->tiles($data['summary']),
            'charts' => $this->charts($data['summary']),
        ], $orientation, 'Moon Traders ERP  |  Company Target Summary  |  '.$data['supplier']->supplier_name);

        return $pdf->download('company-target-summary-'.now()->format('Y-m-d').'.pdf');
    }

    /**
     * Headline figures, in the colours of the report.
     *
     * @param  array<string, mixed>|null  $summary
     * @return array<int, array{label: string, value: string, note: string, color: string}>
     */
    private function tiles(?array $summary): array
    {
        if (! $summary) {
            return [];
        }

        $totals = $summary['totals'];
        $remaining = max(0, $totals['total'] - $totals['achieved']);

        return [
            ['label' => 'Target', 'value' => 'Rs '.SvgChart::money($totals['total']), 'note' => number_format($totals['total']), 'color' => '#2563eb'],
            ['label' => 'Achieved', 'value' => 'Rs '.SvgChart::money($totals['achieved']), 'note' => number_format($totals['achieved']), 'color' => '#16a34a'],
            ['label' => 'Remaining', 'value' => 'Rs '.SvgChart::money($remaining), 'note' => 'Rs '.SvgChart::money($totals['remaining_per_day']).' a day', 'color' => '#f97316'],
            ['label' => 'Achievement', 'value' => number_format($totals['percentage'], 1).'%', 'note' => $summary['rows']->count().' salesmen', 'color' => '#7c3aed'],
            ['label' => 'Days left', 'value' => (string) $summary['days_left'], 'note' => 'working days, Mon-Sat', 'color' => '#0891b2'],
        ];
    }

    /**
     * Three charts: achieved against remaining, percent of target by salesman, target against achieved.
     * Each carries a closure that draws it at the width the page gives it.
     *
     * @param  array<string, mixed>|null  $summary
     * @return array<int, array<string, mixed>>
     */
    private function charts(?array $summary): array
    {
        if (! $summary || $summary['rows']->isEmpty()) {
            return [];
        }

        $rows = $summary['rows'];
        $totals = $summary['totals'];
        $withTarget = $rows->filter(fn (array $row) => $row['total'] > 0);
        $byPercent = $withTarget->sortByDesc('percentage')->take(10)->values();
        $byTarget = $rows->sortByDesc(fn (array $row) => max($row['total'], $row['achieved']))->take(8)->values();

        $bySales = $rows->sortByDesc('achieved')->values();

        if ($totals['total'] <= 0) {
            $top = $bySales->take(5);
            $others = max(0.0, (float) $bySales->skip(5)->sum('achieved'));
            $labels = $top->pluck('employee')->all();
            $values = $top->pluck('achieved')->all();
            if ($others > 0) {
                $labels[] = 'Others';
                $values[] = $others;
            }

            return [
                [
                    'title' => 'Sales share by salesman',
                    'subtitle' => 'No target set for this period, so sales are shown',
                    'accent' => '#16a34a',
                    'svg' => fn (int $w) => SvgChart::donut($labels, $values, SvgChart::PALETTE, $w, 200, SvgChart::money($totals['achieved']), 'sales'),
                ],
                [
                    'title' => 'Sales by salesman',
                    'subtitle' => 'Top 8 in the period',
                    'accent' => '#7c3aed',
                    'svg' => fn (int $w) => SvgChart::bars(
                        $bySales->take(8)->pluck('employee')->all(),
                        $bySales->take(8)->pluck('achieved')->all(),
                        SvgChart::PALETTE, $w, 200, null, 16
                    ),
                ],
                [
                    'title' => 'Sales by salesman (columns)',
                    'subtitle' => 'Biggest first',
                    'accent' => '#2563eb',
                    'wide' => true,
                    'svg' => fn (int $w) => SvgChart::colorColumns(
                        $bySales->take($w < 500 ? 5 : 8)->pluck('employee')->map(fn ($name) => explode(' ', (string) $name)[0])->all(),
                        $bySales->take($w < 500 ? 5 : 8)->pluck('achieved')->all(),
                        SvgChart::PALETTE, $w, 200
                    ),
                ],
            ];
        }

        return [
            [
                'title' => 'Achieved against target',
                'subtitle' => 'Share of the total target reached so far',
                'accent' => '#16a34a',
                'svg' => fn (int $w) => SvgChart::donut(
                    ['Achieved', 'Remaining'],
                    [min($totals['achieved'], $totals['total']), max($totals['total'] - $totals['achieved'], 0)],
                    ['#16a34a', '#f97316'],
                    $w, 200, number_format($totals['percentage'], 0).'%', 'achieved'
                ),
            ],
            [
                'title' => 'Percent of target by salesman',
                'subtitle' => 'Green reached, amber from 70%, red below',
                'accent' => '#7c3aed',
                'svg' => fn (int $w) => SvgChart::achievement(
                    $byPercent->pluck('employee')->all(),
                    $byPercent->pluck('percentage')->all(),
                    $w, 200, 16
                ),
            ],
            [
                'title' => 'Target and achieved by salesman',
                'subtitle' => 'Biggest targets first',
                'accent' => '#2563eb',
                'wide' => true,
                'svg' => fn (int $w) => SvgChart::columns(
                    $byTarget->take($w < 500 ? 5 : 8)->pluck('employee')->map(fn ($name) => explode(' ', (string) $name)[0])->all(),
                    [
                        ['name' => 'Target', 'color' => '#2563eb', 'values' => $byTarget->take($w < 500 ? 5 : 8)->pluck('total')->all()],
                        ['name' => 'Achieved', 'color' => '#16a34a', 'values' => $byTarget->take($w < 500 ? 5 : 8)->pluck('achieved')->all()],
                    ],
                    $w, 200
                ),
            ],
        ];
    }

    /**
     * @return array{supplier: ?Supplier, from: Carbon, to: Carbon, summary: ?array<string, mixed>}
     */
    private function reportData(Request $request, CompanyTargetService $service): array
    {
        $request->validate([
            'supplier_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $user = $request->user();
        $requestedSupplierId = $request->integer('supplier_id') ?: null;

        if ($requestedSupplierId && ! $user->hasAllSupplierAccess() && $requestedSupplierId !== (int) $user->supplier_id) {
            abort(403, 'You do not have permission to view another company.');
        }

        $supplierId = $user->hasAllSupplierAccess() ? $requestedSupplierId : ($user->supplier_id ? (int) $user->supplier_id : null);
        $supplier = $supplierId ? Supplier::query()->where('disabled', false)->find($supplierId, ['id', 'supplier_name']) : null;

        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->input('to'))->startOfDay() : now()->endOfMonth()->startOfDay();

        return [
            'supplier' => $supplier,
            'from' => $from,
            'to' => $to,
            'summary' => $supplier ? $service->summary($supplier->id, $from, $to) : null,
        ];
    }

    /**
     * @return Collection<int, Supplier>
     */
    private function suppliersForUser(): Collection
    {
        $user = auth()->user();

        return Supplier::query()
            ->where('disabled', false)
            ->when(! $user->hasAllSupplierAccess(), fn ($query) => $user->supplier_id
                ? $query->whereKey($user->supplier_id)
                : $query->whereRaw('1 = 0'))
            ->orderBy('supplier_name')
            ->get(['id', 'supplier_name']);
    }
}
