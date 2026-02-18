<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class GoodsReceiptNoteTemplateExport implements FromArray, WithHeadings
{
    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Product Code',
            'Quantity',
            'Unit Price Per Case',
            'Discount Value',
            'FMR Allowance',
            'Excise Duty',
            'Sales Tax Value',
            'Advance Income Tax',
            'Selling Price',
            'Promotional Price',
            'Priority Order',
            'Batch Number',
            'Must Sell Before',
            'Manufacturing Date',
            'Expiry Date',
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        return [
            ['DBP 2.5 KG Tin', 10, 1411.20, 0, 0, 0, null, 0, 1450.68, null, 99, null, null, null, null],
        ];
    }
}

// /**
//  * Column Guide sheet — uncomment and add WithMultipleSheets to the main class if needed later.
//  *
//  * use Maatwebsite\Excel\Concerns\WithMultipleSheets;
//  * use Maatwebsite\Excel\Concerns\WithStyles;
//  * use Maatwebsite\Excel\Concerns\WithTitle;
//  * use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
//  *
//  * class GoodsReceiptNoteTemplateNotesSheet implements FromArray, WithHeadings, WithStyles, WithTitle
//  * {
//  *     public function title(): string
//  *     {
//  *         return 'Column Guide';
//  *     }
//  *
//  *     public function headings(): array
//  *     {
//  *         return ['Column Name', 'Required', 'Description', 'Example'];
//  *     }
//  *
//  *     public function array(): array
//  *     {
//  *         return [
//  *             ['Product Code', 'Yes', 'Product code from the system. Must belong to the selected supplier.', 'DBP 2.5 KG Tin'],
//  *             ['Quantity', 'Yes', 'Quantity in purchase UOM (Cases). Must be greater than 0.', '10'],
//  *             ['Unit Price Per Case', 'Yes', 'Invoice price per case. Must be greater than 0.', '1500.00'],
//  *             ['Discount Value', 'No', 'Discount amount. Defaults to 0.', '200.00'],
//  *             ['FMR Allowance', 'No', 'Free Market Rate allowance. Included in unit cost. Defaults to 0.', '100.00'],
//  *             ['Excise Duty', 'No', 'Excise duty amount. Defaults to 0.', '50.00'],
//  *             ['Sales Tax Value', 'No', 'Sales tax amount. Leave blank to auto-calculate from supplier tax rate.', '234.00'],
//  *             ['Advance Income Tax', 'No', 'Advance income tax amount. Defaults to 0.', '25.00'],
//  *             ['Selling Price', 'No', 'Selling price per unit. Defaults to product selling price. Overridden by promotional price.', '75.00'],
//  *             ['Promotional Price', 'No', 'Promotional selling price. Also sets selling price. Creates a promotional campaign.', '65.00'],
//  *             ['Priority Order', 'No', 'Selling priority 1-99. 1=Highest (sell first), 99=Normal FIFO.', '99'],
//  *             ['Batch Number', 'No', 'Supplier batch code. Optional.', 'BATCH-001'],
//  *             ['Must Sell Before', 'No', 'Deadline date to sell this batch. Format: YYYY-MM-DD.', '2026-06-30'],
//  *             ['Manufacturing Date', 'No', 'Manufacturing date. Format: YYYY-MM-DD.', '2025-01-01'],
//  *             ['Expiry Date', 'No', 'Expiry date. Format: YYYY-MM-DD.', '2027-01-01'],
//  *             ['', '', '', ''],
//  *             ['=== AUTO-CALCULATED FIELDS ===', '', '', ''],
//  *             ['purchase_uom', 'Auto', 'Defaults to Case (UOM ID 33). Not editable via import.', 'Case'],
//  *             ['uom_conversion_factor', 'Auto', 'Fetched from product configuration. 1 Case = X Pieces.', '24'],
//  *             ['qty_in_stock_uom', 'Auto', 'Quantity × uom_conversion_factor', '240'],
//  *             ['extended_value', 'Auto', 'Quantity × Unit Price Per Case', '15000.00'],
//  *             ['discounted_value_before_tax', 'Auto', 'extended_value - Discount Value - FMR Allowance', '14700.00'],
//  *             ['total_value_with_taxes', 'Auto', 'discounted + Excise Duty + Sales Tax + Advance Income Tax', '15009.00'],
//  *             ['unit_cost', 'Auto', '(total_value_with_taxes + FMR Allowance) / quantity_received', '62.95'],
//  *         ];
//  *     }
//  *
//  *     public function styles(Worksheet $sheet): array
//  *     {
//  *         $sheet->getColumnDimension('A')->setWidth(28);
//  *         $sheet->getColumnDimension('B')->setWidth(10);
//  *         $sheet->getColumnDimension('C')->setWidth(65);
//  *         $sheet->getColumnDimension('D')->setWidth(15);
//  *
//  *         return [
//  *             1 => [
//  *                 'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
//  *                 'fill' => [
//  *                     'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
//  *                     'startColor' => ['rgb' => '1E3A5F'],
//  *                 ],
//  *             ],
//  *         ];
//  *     }
//  * }
//  */
