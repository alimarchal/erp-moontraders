<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalesSettlementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'settlement_date' => 'required|date',
            'goods_issue_id' => 'required|exists:goods_issues,id',
            'cash_sales_amount' => 'nullable|numeric|min:0',
            'cheque_sales_amount' => 'nullable|numeric|min:0',
            'credit_sales_amount' => 'nullable|numeric|min:0',
            'cash_collected' => 'nullable|numeric|min:0',
            'cheques_collected' => 'nullable|numeric|min:0',
            'expenses_claimed' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',

            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity_issued' => 'required|numeric|min:0',
            'items.*.quantity_sold' => 'required|numeric|min:0',
            'items.*.quantity_returned' => 'nullable|numeric|min:0',
            'items.*.quantity_shortage' => 'nullable|numeric|min:0',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.selling_price' => 'required|numeric|min:0',

            'sales' => 'nullable|array',
            'sales.*.customer_id' => 'required_with:sales|exists:customers,id',
            'sales.*.invoice_number' => 'nullable|string|max:100',
            'sales.*.sale_amount' => 'required_with:sales|numeric|min:0',
            'sales.*.payment_type' => 'required_with:sales|in:cash,cheque,credit',
        ];
    }
}
