<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class StoreGoodsIssueRequest extends FormRequest
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

                    $employeeId = $this->input('employee_id');

                    if ($vehicle->employee_id !== null && (int) $vehicle->employee_id !== (int) $employeeId) {
                        $fail('The selected vehicle does not belong to the selected salesman.');
                    }
                },
                function ($attribute, $value, $fail) {
                    $unposted = DB::table('sales_settlements')
                        ->where('vehicle_id', $value)
                        ->whereIn('status', ['draft', 'verified'])
                        ->whereNull('deleted_at')
                        ->first();

                    if ($unposted) {
                        $fail("Cannot create a Goods Issue for this vehicle: settlement {$unposted->settlement_number} is not yet posted. Post all pending settlements before issuing new stock.");
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
