<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class OpeningStockTemplateExport implements FromArray, WithHeadings
{
    public function __construct(private int $supplierId) {}

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'SKU',
            'Invoice Price',
            'Retail Price',
            'Total Inventory in Pieces',
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        return Product::where('supplier_id', $this->supplierId)
            ->where('is_active', true)
            ->orderBy('product_name')
            ->pluck('product_code')
            ->map(fn (string $code) => [$code, null, null, null])
            ->toArray();
    }
}
