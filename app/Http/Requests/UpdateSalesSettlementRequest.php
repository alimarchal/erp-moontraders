<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSalesSettlementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Decode JSON strings from modals before validation
        if ($this->has('credit_sales') && is_string($this->credit_sales)) {
            $this->merge([
                'credit_sales' => json_decode($this->credit_sales, true),
            ]);
        }

        if ($this->has('advance_taxes') && is_string($this->advance_taxes)) {
            $this->merge([
                'advance_taxes' => json_decode($this->advance_taxes, true),
            ]);
        }

        if ($this->has('bank_transfers') && is_string($this->bank_transfers)) {
            $this->merge([
                'bank_transfers' => json_decode($this->bank_transfers, true),
            ]);
        }

        if ($this->has('cheques') && is_string($this->cheques)) {
            $this->merge([
                'cheques' => json_decode($this->cheques, true),
            ]);
        }
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
            'bank_sales_amount' => 'nullable|numeric|min:0',
            'credit_sales_amount' => 'nullable|numeric|min:0',
            'cash_collected' => 'nullable|numeric|min:0',
            'cheques_collected' => 'nullable|numeric|min:0',
            'expenses_claimed' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',

            // Item level validation
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.goods_issue_item_id' => 'nullable|exists:goods_issue_items,id',
            'items.*.quantity_issued' => 'required|numeric|min:0',
            'items.*.quantity_sold' => 'required|numeric|min:0',
            'items.*.quantity_returned' => 'nullable|numeric|min:0',
            'items.*.quantity_shortage' => 'nullable|numeric|min:0',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.selling_price' => 'nullable|numeric|min:0',

            // Batch level validation
            'items.*.batches' => 'nullable|array',
            'items.*.batches.*.stock_batch_id' => 'required|exists:stock_batches,id',
            'items.*.batches.*.batch_code' => 'nullable|string|max:100',
            'items.*.batches.*.quantity_issued' => 'required|numeric|min:0',
            'items.*.batches.*.quantity_sold' => 'nullable|numeric|min:0',
            'items.*.batches.*.quantity_returned' => 'nullable|numeric|min:0',
            'items.*.batches.*.quantity_shortage' => 'nullable|numeric|min:0',
            'items.*.batches.*.unit_cost' => 'required|numeric|min:0',
            'items.*.batches.*.selling_price' => 'required|numeric|min:0',
            'items.*.batches.*.is_promotional' => 'nullable|boolean',

            'sales' => 'nullable|array',
            'sales.*.customer_id' => 'required_with:sales|exists:customers,id',
            'sales.*.invoice_number' => 'nullable|string|max:100',
            'sales.*.sale_amount' => 'required_with:sales|numeric|min:0',
            'sales.*.payment_type' => 'required_with:sales|in:cash,cheque,credit',

            // Credit sales breakdown
            'credit_sales' => 'nullable|array',
            'credit_sales.*.customer_id' => 'required_with:credit_sales|exists:customers,id',
            'credit_sales.*.invoice_number' => 'nullable|string|max:100',
            'credit_sales.*.sale_amount' => 'required_with:credit_sales|numeric|min:0',
            'credit_sales.*.payment_received' => 'nullable|numeric|min:0',
            'credit_sales.*.previous_balance' => 'nullable|numeric',
            'credit_sales.*.new_balance' => 'nullable|numeric',
            'credit_sales.*.notes' => 'nullable|string',

            // Advance tax breakdown
            'advance_taxes' => 'nullable|array',
            'advance_taxes.*.customer_id' => 'required_with:advance_taxes|exists:customers,id',
            'advance_taxes.*.sale_amount' => 'nullable|numeric|min:0',
            'advance_taxes.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'advance_taxes.*.tax_amount' => 'required_with:advance_taxes|numeric|min:0',
            'advance_taxes.*.invoice_number' => 'nullable|string|max:100',
            'advance_taxes.*.notes' => 'nullable|string',

            // Bank transfers
            'bank_transfers' => 'nullable|array',
            'bank_transfers.*.customer_id' => 'nullable|exists:customers,id',
            'bank_transfers.*.amount' => 'required_with:bank_transfers|numeric|min:0',
            'bank_transfers.*.reference' => 'nullable|string|max:100',

            // Cheques
            'cheques' => 'nullable|array',
            'cheques.*.customer_id' => 'nullable|exists:customers,id',
            'cheques.*.amount' => 'required_with:cheques|numeric|min:0',
            'cheques.*.cheque_number' => 'nullable|string|max:100',
            'cheques.*.bank_name' => 'nullable|string|max:100',
            'cheques.*.cheque_date' => 'nullable|date',
        ];
    }
}
