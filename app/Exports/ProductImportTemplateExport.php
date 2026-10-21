<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProductImportTemplateExport implements FromArray, ShouldAutoSize, WithHeadings
{
    /**
     * Same columns as the product Excel export, so an exported file can be re-imported.
     *
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Product Code', 'Product Name', 'Brand', 'Barcode', 'Category', 'Supplier', 'UOM', 'Sales UOM',
            'Valuation Method', 'Sell Price', 'Cost Price', 'Reorder Level', 'Pack Size', 'Weight',
            'Expiry Price', 'Powder?', 'Status', 'Conversion Factor',
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        return [
            ['SKU-001', 'Sample Product 1L', 'Brand', '', 'Cooking Oil', 'Supplier Name', 'Piece', 'Case', 'FIFO', 626, 596, 0, '1ltr', '', 0, 'No', 'Active', 5],
        ];
    }
}
