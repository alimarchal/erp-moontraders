<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class StoreGoodsIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        // Flash the vehicle block error as a session alert (beep + red notification)
        // so it appears prominently via <x-status-message>, not just in the validation list.
        $vehicleErrors = $validator->errors()->get('vehicle_id');
        foreach ($vehicleErrors as $message) {
            if (str_contains($message, 'Cannot create a Goods Issue for vehicle')) {
                $this->session()->flash('error', 'Failed to create Goods Issue: '.$message);
                break;
            }
        }

        parent::failedValidation($validator);
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
                    $unsettled = DB::table('goods_issues')
                        ->select(
                            'goods_issues.id',
                            'goods_issues.issue_number',
                            'sales_settlements.settlement_number',
                            'sales_settlements.status as settlement_status',
                            'vehicles.registration_number'
                        )
                        ->leftJoin('sales_settlements', function ($join) {
                            $join->on('sales_settlements.goods_issue_id', '=', 'goods_issues.id')
                                ->whereNull('sales_settlements.deleted_at');
                        })
                        ->leftJoin('vehicles', 'vehicles.id', '=', 'goods_issues.vehicle_id')
                        ->where('goods_issues.vehicle_id', $value)
                        ->where('goods_issues.status', 'issued')
                        ->where(function ($q) {
                            $q->whereNull('sales_settlements.id')
                                ->orWhereIn('sales_settlements.status', ['draft', 'verified']);
                        })
                        ->first();

                    if ($unsettled) {
                        $vehicle = $unsettled->registration_number ?? "Vehicle #{$value}";
                        if ($unsettled->settlement_number) {
                            $status = ucfirst($unsettled->settlement_status);
                            $fail("Cannot create a Goods Issue for vehicle {$vehicle}: settlement {$unsettled->settlement_number} ({$status}) for {$unsettled->issue_number} is not yet posted. Post all pending settlements before issuing new stock.");
                        } else {
                            $fail("Cannot create a Goods Issue for vehicle {$vehicle}: {$unsettled->issue_number} has been issued but has no settlement yet. Create and post its settlement before issuing new stock.");
                        }
                    }
                },
            ],
            'employee_id' => 'required|exists:employees,id',
            'supplier_ids' => 'nullable|array|min:1',
            'supplier_ids.*' => 'required|exists:suppliers,id',
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
