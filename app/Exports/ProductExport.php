<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductExport implements FromQuery, WithHeadings, WithMapping
{
    /**
     * @param  Builder<\App\Models\Product>  $query
     */
    public function __construct(private Builder $query) {}

    public function query(): Builder
    {
        return $this->query;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Product Code',
            'Product Name',
            'Brand',
            'Barcode',
            'Category',
            'Supplier',
            'UOM',
            'Sales UOM',
            'Valuation Method',
            'Sell Price',
            'Cost Price',
            'Reorder Level',
            'Pack Size',
            'Weight',
            'Expiry Price',
            'Powder?',
            'Status',
        ];
    }

    /**
     * @param  \App\Models\Product  $product
     * @return array<int, mixed>
     */
    public function map($product): array
    {
        return [
            $product->product_code,
            $product->product_name,
            $product->brand ?? '',
            $product->barcode ?? '',
            $product->category?->name ?? '',
            $product->supplier?->supplier_name ?? '',
            $product->uom?->uom_name ?? '',
            $product->salesUom?->uom_name ?? '',
            $product->valuation_method ?? '',
            $product->unit_sell_price,
            $product->cost_price,
            $product->reorder_level,
            $product->pack_size ?? '',
            $product->weight ?? '',
            $product->expiry_price ?? '',
            $product->is_powder ? 'Yes' : 'No',
            $product->is_active ? 'Active' : 'Inactive',
        ];
    }
}
