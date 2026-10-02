<?php

namespace App\Http\Requests;

use App\Models\GoodsIssue;
use App\Services\GoodsIssueStockCheck;
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
            'vehicle_id' => [
                'required',
                'exists:vehicles,id',
                function ($attribute, $value, $fail) {
                    $vehicle = DB::table('vehicles')->where('id', $value)->first();

                    if (! $vehicle) {
                        return;
                    }

                    $supplierIds = (array) $this->input('supplier_ids');

                    if (! empty($supplierIds) && $vehicle->supplier_id !== null && ! in_array((int) $vehicle->supplier_id, array_map('intval', $supplierIds))) {
                        $fail('The selected vehicle does not belong to the selected supplier.');
                    }
                },
                function ($attribute, $value, $fail) {
                    // Moving a draft onto a vehicle that another active issue holds would hit the
                    // active_vehicle_lock unique index; name the blocking issue instead.
                    $blocking = DB::table('goods_issues')
                        ->where('active_vehicle_lock', $value)
                        ->where('id', '!=', $this->goodsIssueId())
                        ->value('issue_number');

                    if ($blocking) {
                        $fail("This vehicle already has an active Goods Issue ({$blocking}). Post its settlement, or delete it if it is a draft, before moving this issue onto the vehicle.");
                    }
                },
            ],
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
                    $uomId = $this->input("items.{$index}.uom_id");
                    $mustBeWholeNumber = $uomId && DB::table('uoms')->where('id', $uomId)->value('must_be_whole_number');

                    if ($mustBeWholeNumber && $value != floor($value)) {
                        $uomName = DB::table('uoms')->where('id', $uomId)->value('uom_name');
                        $fail("The quantity ({$value}) must be a whole number for UOM '{$uomName}'.");
                    }
                },
                function ($attribute, $value, $fail) {
                    $index = explode('.', $attribute)[1];
                    $productId = $this->input("items.{$index}.product_id");
                    $warehouseId = $this->input('warehouse_id');
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
                            $product = DB::table('products')->where('id', $productId)->first(['product_name', 'uom_conversion_factor']);
                            $factor = (float) ($product->uom_conversion_factor ?? 1);
                            $suffix = $excludePromotional ? ' (non-promotional only)' : '';
                            $fail(sprintf(
                                'The quantity for %s (%s) exceeds available stock (%s)%s.',
                                $product->product_name ?? "product #{$productId}",
                                GoodsIssueStockCheck::formatQuantity((float) $value, $factor),
                                GoodsIssueStockCheck::formatQuantity((float) $availableStock, $factor),
                                $suffix
                            ));
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

    /**
     * The issue being edited. The resource route names its parameter `goods_issue`,
     * so `route('goodsIssue')` was always null and the draft was blocked by its own lock.
     */
    private function goodsIssueId(): int
    {
        $goodsIssue = $this->route('goods_issue');

        return (int) ($goodsIssue instanceof GoodsIssue ? $goodsIssue->getKey() : $goodsIssue);
    }
}
