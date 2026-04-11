<?php

namespace App\Http\Requests;

use App\Models\GoodsIssue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class AppendGoodsIssueItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var GoodsIssue|null $goodsIssue */
        $goodsIssue = $this->route('goodsIssue');
        $warehouseId = $goodsIssue?->warehouse_id;

        return [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity_issued' => [
                'required',
                'numeric',
                'min:0.001',
                function ($attribute, $value, $fail) use ($warehouseId) {
                    $index = explode('.', $attribute)[1];
                    $productId = $this->input("items.{$index}.product_id");
                    $excludePromotional = (bool) $this->input("items.{$index}.exclude_promotional");

                    if ($productId && $warehouseId) {
                        $query = DB::table('stock_valuation_layers')
                            ->where('warehouse_id', $warehouseId)
                            ->where('product_id', $productId)
                            ->where('is_depleted', false)
                            ->where('quantity_remaining', '>', 0);

                        if ($excludePromotional) {
                            $query->where('is_promotional', false);
                        }

                        $availableStock = $query->sum('quantity_remaining');

                        if ($value > $availableStock) {
                            $productName = DB::table('products')->where('id', $productId)->value('product_name');
                            $suffix = $excludePromotional ? ' (non-promotional only)' : '';
                            $fail("The quantity for {$productName} ({$value}) exceeds available stock ({$availableStock}){$suffix}.");
                        }
                    }
                },
            ],
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.selling_price' => 'required|numeric|min:0',
            'items.*.uom_id' => 'required|exists:uoms,id',
            'items.*.exclude_promotional' => 'nullable|boolean',
        ];
    }
}
