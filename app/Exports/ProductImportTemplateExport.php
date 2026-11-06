<?php

namespace App\Exports;

use App\Models\Product;
use App\Models\Supplier;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProductImportTemplateExport implements FromArray, ShouldAutoSize, WithHeadings
{
    /** @param  Supplier|null  $supplier  when given, the sheet lists that company's products with their current values */
    public function __construct(private ?Supplier $supplier = null) {}

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
        if ($this->supplier !== null) {
            $rows = Product::query()
                ->where('supplier_id', $this->supplier->id)
                ->with(['category:id,name', 'uom:id,uom_name', 'salesUom:id,uom_name'])
                ->orderBy('product_name')
                ->get()
                ->map(fn (Product $p) => [
                    $p->product_code, $p->product_name, $p->brand ?? '', $p->barcode ?? '', $p->category?->name ?? '', $this->supplier->supplier_name,
                    $p->uom?->uom_name ?? '', $p->salesUom?->uom_name ?? '', $p->valuation_method ?? '', $p->unit_sell_price, $p->cost_price,
                    $p->reorder_level, $p->pack_size ?? '', $p->weight ?? '', $p->expiry_price ?? '', $p->is_powder ? 'Yes' : 'No',
                    $p->is_active ? 'Active' : 'Inactive', $p->uom_conversion_factor,
                ])->all();

            // A company with no products yet still gets one example row, already carrying its name.
            return $rows ?: [['SKU-001', 'Sample Product 1L', 'Brand', '', '', $this->supplier->supplier_name, 'Piece', '', 'FIFO', 626, 596, 0, '1ltr', '', 0, 'No', 'Active', 1]];
        }

        return [
            ['SKU-001', 'Sample Product 1L', 'Brand', '', 'Cooking Oil', 'Supplier Name', 'Piece', 'Case', 'FIFO', 626, 596, 0, '1ltr', '', 0, 'No', 'Active', 5],
        ];
    }
}
