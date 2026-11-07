<?php

namespace App\Exports;

use App\Http\Controllers\Reports\DailySalesReportController;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Product wise sales report: the same rows and visible columns as the screen.
 */
class ProductWiseSalesExport implements FromArray, ShouldAutoSize, WithStyles
{
    private int $headingRow;

    /**
     * @param  Collection<int, object>  $rows
     * @param  array<string, float|int>  $totals
     * @param  array<int, string>  $columns
     * @param  array<int, string>  $filterLines
     */
    public function __construct(
        private Collection $rows,
        private array $totals,
        private array $columns,
        private array $filterLines,
        private string $startDate,
        private string $endDate,
    ) {}

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        $out = [
            [config('app.name').' - SKU Wise Sales Report'],
            ['Period: '.$this->startDate.' to '.$this->endDate],
        ];
        if ($this->filterLines) {
            $out[] = [implode(' | ', $this->filterLines)];
        }
        $out[] = [];
        $this->headingRow = count($out) + 1;

        $labels = DailySalesReportController::OPTIONAL_COLUMNS;
        $has = fn (string $key) => in_array($key, $this->columns, true);
        $heading = ['Sr#', 'Product Name'];
        foreach (['category', 'brand', 'branch', 'avg_price'] as $key) {
            if ($has($key)) {
                $heading[] = $labels[$key];
            }
        }
        $heading[] = 'Sold Qty';
        foreach (['returned', 'shortage', 'net_sales', 'net_profit', 'np_percent', 'prev_sales', 'change', 'change_pct'] as $key) {
            if ($has($key)) {
                $heading[] = $labels[$key];
            }
        }
        $out[] = $heading;

        foreach ($this->rows as $i => $row) {
            $out[] = $this->line([$i + 1, $row->product_name], $row->category_name ?? '', $row->brand ?? '', $row->branch_names, round($row->avg_selling_price, 2), (float) $row->total_sold, $row->total_returned, $row->total_shortage, $row->total_sales_value, $row->net_profit, $row->previous_sales, $row->sales_change);
        }
        $t = $this->totals;
        $out[] = $this->line(['', 'Total ('.$this->rows->count().' rows)'], '', '', '', '', (float) $t['total_sold'], $t['total_returned'], $t['total_shortage'], $t['total_sales_value'], $t['net_profit'], $t['previous_sales'], $t['sales_change']);

        return $out;
    }

    /**
     * One output line holding only the visible columns.
     *
     * @param  array<int, mixed>  $lead
     * @return array<int, mixed>
     */
    private function line(array $lead, string $category, string $brand, string $branch, float|int|string $avg, float $sold, float|int|string $returned, float|int|string $shortage, float|int|string $sales, float|int|string $profit, float|int|string $previous, float|int|string $change): array
    {
        $has = fn (string $key) => in_array($key, $this->columns, true);
        $line = $lead;
        foreach ([['category', $category], ['brand', $brand], ['branch', $branch], ['avg_price', $avg]] as [$key, $value]) {
            if ($has($key)) {
                $line[] = $value;
            }
        }
        $line[] = $sold;
        $optional = [
            'returned' => (float) $returned,
            'shortage' => (float) $shortage,
            'net_sales' => round((float) $sales, 2),
            'net_profit' => round((float) $profit, 2),
            'np_percent' => $sales > 0 ? round($profit / $sales * 100, 2) : 0,
            'prev_sales' => round((float) $previous, 2),
            'change' => round((float) $change, 2),
            'change_pct' => $previous > 0 ? round($change / $previous * 100, 2) : 'New',
        ];
        foreach ($optional as $key => $value) {
            if ($has($key)) {
                $line[] = $value;
            }
        }

        return $line;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            $this->headingRow => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E2E8F0']]],
            $sheet->getHighestRow() => ['font' => ['bold' => true]],
        ];
    }
}
