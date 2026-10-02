<?php

namespace App\Exports;

use App\Models\GoodsIssue;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/**
 * The Goods Issues list as an Excel sheet, with the same filters and sort as the screen.
 *
 * The query arrives from the list with its relations and item count already loaded.
 */
class GoodsIssueExport extends DefaultValueBinder implements FromQuery, WithCustomValueBinder, WithHeadings, WithMapping
{
    /**
     * @param  Builder<GoodsIssue>  $query
     */
    public function __construct(private Builder $query) {}

    public function query(): Builder
    {
        return $this->query;
    }

    /**
     * Text is written as text, so a name typed as "=..." cannot run as a formula when the sheet is opened.
     */
    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Issue Number',
            'Issue Date',
            'Status',
            'Supplier',
            'Salesman',
            'Salesman Code',
            'Vehicle',
            'Warehouse',
            'Lines',
            'Value',
            'Settlement',
            'Settlement Status',
            'Created By',
            'Posted At',
        ];
    }

    /**
     * @param  GoodsIssue  $goodsIssue
     * @return array<int, mixed>
     */
    public function map($goodsIssue): array
    {
        $settlement = $goodsIssue->settlement->sortByDesc('id')->first();

        return [
            $goodsIssue->issue_number,
            $goodsIssue->issue_date?->format('Y-m-d'),
            $goodsIssue->status === 'cancelled' ? 'Reversed' : ucfirst((string) $goodsIssue->status),
            $goodsIssue->supplier?->supplier_name ?? '',
            $goodsIssue->employee?->name ?? '',
            $goodsIssue->employee?->employee_code ?? '',
            $goodsIssue->vehicle?->vehicle_number ?? '',
            $goodsIssue->warehouse?->warehouse_name ?? '',
            (int) $goodsIssue->items_count,
            (float) $goodsIssue->total_value,
            $settlement?->settlement_number ?? '',
            $settlement ? ucfirst((string) $settlement->status) : ($goodsIssue->status === 'issued' ? 'Not settled' : ''),
            $goodsIssue->issuedBy?->name ?? '',
            $goodsIssue->posted_at?->format('Y-m-d H:i') ?? '',
        ];
    }
}
