<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class UpdateGoodsIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'issue_date' => 'required|date',
            'warehouse_id' => 'required|exists:warehouses,id',
            'vehicle_id' => 'required|exists:vehicles,id',
            'employee_id' => 'required|exists:employees,id',
            'notes' => 'nullable|string',

            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity_issued' => [
                'required',
                'numeric',
                'min:0.001',
                function ($attribute, $value, $fail) {
                    $index = explode('.', $attribute)[1];
                    $productId = $this->input("items.{$index}.product_id");
                    $warehouseId = $this->input('warehouse_id');

                    if ($productId && $warehouseId) {
                        $availableStock = DB::table('stock_valuation_layers')
                            ->where('warehouse_id', $warehouseId)
                            ->where('product_id', $productId)
                            ->where('is_depleted', false)
                            ->where('quantity_remaining', '>', 0)
                            ->sum('quantity_remaining');

                        if ($value > $availableStock) {
                            $productName = DB::table('products')->where('id', $productId)->value('product_name');
                            $fail("The quantity for {$productName} ({$value}) exceeds available stock ({$availableStock}).");
                        }
                    }
                },
            ],
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.uom_id' => 'required|exists:uoms,id',
        ];
    }
}
