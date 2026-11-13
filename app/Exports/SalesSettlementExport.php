<?php

namespace App\Exports;

use App\Models\SalesSettlement;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/**
 * The Sales Settlements list as an Excel sheet, with the same filters and sort as the screen.
 *
 * The query arrives from the list with its relations and sales / COGS / expense sums already loaded.
 */
class SalesSettlementExport extends DefaultValueBinder implements FromQuery, WithCustomValueBinder, WithHeadings, WithMapping
{
    /**
     * @param  Builder<SalesSettlement>  $query
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
            'Settlement Number',
            'Settlement Date',
            'Status',
            'Goods Issue',
            'Supplier',
            'Salesman',
            'Vehicle',
            'Warehouse',
            'Sales',
            'Cost of Goods Sold',
            'Gross Profit',
            'Expenses',
            'Net Profit',
            'Created By',
            'Posted At',
        ];
    }

    /**
     * @param  SalesSettlement  $settlement
     * @return array<int, mixed>
     */
    public function map($settlement): array
    {
        $sales = $settlement->calculated_total_sales_amount;
        $cogs = $settlement->calculated_total_cogs;

        return [
            $settlement->settlement_number,
            $settlement->settlement_date?->format('Y-m-d'),
            ucfirst((string) $settlement->status),
            $settlement->goodsIssue?->issue_number ?? '',
            $settlement->supplier?->supplier_name ?? '',
            $settlement->employee?->name ?? '',
            $settlement->vehicle?->vehicle_number ?? '',
            $settlement->warehouse?->warehouse_name ?? '',
            $sales,
            $cogs,
            $sales - $cogs,
            $settlement->calculated_total_expenses,
            $settlement->calculated_net_profit,
            $settlement->creator?->name ?? '',
            $settlement->posted_at?->format('Y-m-d H:i') ?? '',
        ];
    }
}
