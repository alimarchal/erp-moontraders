<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GoodsReceiptNoteTemplateExport implements WithMultipleSheets
{
    /**
     * @return array<int, GoodsReceiptNoteTemplateDataSheet|GoodsReceiptNoteTemplateNotesSheet>
     */
    public function sheets(): array
    {
        return [
            new GoodsReceiptNoteTemplateDataSheet,
            new GoodsReceiptNoteTemplateNotesSheet,
        ];
    }
}

class GoodsReceiptNoteTemplateDataSheet implements FromArray, WithHeadings, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'GRN Items';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'product_code',
            'qty_in_purchase_uom',
            'unit_price_per_case',
            'discount_value',
            'fmr_allowance',
            'excise_duty',
            'sales_tax_value',
            'advance_income_tax',
            'selling_price',
            'promotional_price',
            'priority_order',
            'must_sell_before',
            'batch_number',
            'manufacturing_date',
            'expiry_date',
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        return [
            ['PROD-001', 10, 1500.00, 200.00, 100.00, 50.00, '', 25.00, 75.00, '', 99, '', 'BATCH-001', '', ''],
            ['PROD-002', 5, 2000.00, 0, 0, 0, '', 0, 100.00, 85.00, 1, '2026-06-30', '', '2025-01-01', '2027-01-01'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1E3A5F'],
                ],
            ],
        ];
    }
}

class GoodsReceiptNoteTemplateNotesSheet implements FromArray, WithHeadings, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Column Guide';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Column Name', 'Required', 'Description', 'Example'];
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function array(): array
    {
        return [
            ['product_code', 'Yes', 'Product code from the system. Must belong to the selected supplier.', 'PROD-001'],
            ['qty_in_purchase_uom', 'Yes', 'Quantity in purchase UOM (Cases). Must be greater than 0.', '10'],
            ['unit_price_per_case', 'Yes', 'Invoice price per case. Must be greater than 0.', '1500.00'],
            ['discount_value', 'No', 'Discount amount. Defaults to 0.', '200.00'],
            ['fmr_allowance', 'No', 'Free Market Rate allowance. Included in unit cost. Defaults to 0.', '100.00'],
            ['excise_duty', 'No', 'Excise duty amount. Defaults to 0.', '50.00'],
            ['sales_tax_value', 'No', 'Sales tax amount. Leave blank to auto-calculate from supplier tax rate.', '234.00'],
            ['advance_income_tax', 'No', 'Advance income tax amount. Defaults to 0.', '25.00'],
            ['selling_price', 'No', 'Selling price per unit. Defaults to product\'s configured selling price.', '75.00'],
            ['promotional_price', 'No', 'Promotional selling price. Creates a promotional campaign automatically.', '65.00'],
            ['priority_order', 'No', 'Selling priority 1-99. 1=Highest (sell first), 99=Normal FIFO.', '99'],
            ['must_sell_before', 'No', 'Deadline date to sell this batch. Format: YYYY-MM-DD.', '2026-06-30'],
            ['batch_number', 'No', 'Supplier batch code. Optional.', 'BATCH-001'],
            ['manufacturing_date', 'No', 'Manufacturing date. Format: YYYY-MM-DD.', '2025-01-01'],
            ['expiry_date', 'No', 'Expiry date. Format: YYYY-MM-DD.', '2027-01-01'],
            ['', '', '', ''],
            ['=== CALCULATION NOTES ===', '', '', ''],
            ['purchase_uom', 'Auto', 'Defaults to Case (UOM ID 33). Not editable via import.', 'Case'],
            ['uom_conversion_factor', 'Auto', 'Fetched from product configuration. 1 Case = X Pieces.', '24'],
            ['qty_in_stock_uom', 'Auto', 'qty_in_purchase_uom × uom_conversion_factor', '240'],
            ['extended_value', 'Auto', 'qty_in_purchase_uom × unit_price_per_case', '15000.00'],
            ['discounted_value_before_tax', 'Auto', 'extended_value - discount_value - fmr_allowance', '14700.00'],
            ['total_value_with_taxes', 'Auto', 'discounted_value_before_tax + excise_duty + sales_tax + advance_income_tax', '15009.00'],
            ['unit_cost', 'Auto', '(total_value_with_taxes + fmr_allowance) / quantity_received', '62.95'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(10);
        $sheet->getColumnDimension('C')->setWidth(65);
        $sheet->getColumnDimension('D')->setWidth(15);

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1E3A5F'],
                ],
            ],
        ];
    }
}
