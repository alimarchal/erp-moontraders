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

        $showAvg = in_array('avg_price', $this->columns, true);
        $heading = $showAvg ? ['Sr#', 'Product Name', 'Avg Price', 'Sold Qty'] : ['Sr#', 'Product Name', 'Sold Qty'];
        foreach (array_diff($this->columns, ['avg_price']) as $key) {
            $heading[] = DailySalesReportController::OPTIONAL_COLUMNS[$key];
        }
        $out[] = $heading;

        foreach ($this->rows as $i => $row) {
            $out[] = array_merge($showAvg ? [$i + 1, $row->product_name, round($row->avg_selling_price, 2), (float) $row->total_sold] : [$i + 1, $row->product_name, (float) $row->total_sold], $this->optional($row->total_returned, $row->total_shortage, $row->total_sales_value, $row->net_profit));
        }
        $out[] = array_merge($showAvg ? ['', 'Total ('.$this->rows->count().' rows)', '', (float) $this->totals['total_sold']] : ['', 'Total ('.$this->rows->count().' rows)', (float) $this->totals['total_sold']], $this->optional($this->totals['total_returned'], $this->totals['total_shortage'], $this->totals['total_sales_value'], $this->totals['net_profit']));

        return $out;
    }

    /**
     * @return array<int, mixed>
     */
    private function optional(float|int|string $returned, float|int|string $shortage, float|int|string $sales, float|int|string $profit): array
    {
        $values = [
            'returned' => (float) $returned,
            'shortage' => (float) $shortage,
            'net_sales' => round($sales, 2),
            'net_profit' => round($profit, 2),
            'np_percent' => $sales > 0 ? round($profit / $sales * 100, 2) : 0,
        ];

        return array_map(fn (string $key) => $values[$key], array_values(array_diff($this->columns, ['avg_price'])));
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
