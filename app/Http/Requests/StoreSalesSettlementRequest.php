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

        if ($this->has('recoveries_entries') && is_string($this->recoveries_entries)) {
            $this->merge([
                'recoveries_entries' => json_decode($this->recoveries_entries, true),
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
            'bank_transfer_amount' => 'nullable|numeric|min:0',
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

            // Sales/Credit sales validation
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
            'credit_sales.*.notes' => 'nullable|string',

            // Recoveries breakdown
            'recoveries_entries' => 'nullable|array',
            'recoveries_entries.*.customer_id' => 'required_with:recoveries_entries|exists:customers,id',
            'recoveries_entries.*.recovery_number' => 'nullable|string|max:100',
            'recoveries_entries.*.payment_method' => 'required_with:recoveries_entries|in:cash,bank_transfer',
            'recoveries_entries.*.bank_account_id' => 'required_if:recoveries_entries.*.payment_method,bank_transfer|nullable|exists:bank_accounts,id',
            'recoveries_entries.*.amount' => 'required_with:recoveries_entries|numeric|min:0',
            'recoveries_entries.*.previous_balance' => 'nullable|numeric',
            'recoveries_entries.*.new_balance' => 'nullable|numeric',
            'recoveries_entries.*.notes' => 'nullable|string',

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

            // Expense fields
            'expense_toll_tax' => 'nullable|numeric|min:0',
            'expense_amr_powder_claim' => 'nullable|numeric|min:0',
            'expense_amr_liquid_claim' => 'nullable|numeric|min:0',
            'expense_scheme' => 'nullable|numeric|min:0',
            'expense_advance_tax' => 'nullable|numeric|min:0',
            'expense_food_charges' => 'nullable|numeric|min:0',
            'expense_percentage' => 'nullable|numeric|min:0',
            'expense_miscellaneous_amount' => 'nullable|numeric|min:0',

            // Cash denomination
            'denom_5000' => 'nullable|numeric|min:0',
            'denom_1000' => 'nullable|numeric|min:0',
            'denom_500' => 'nullable|numeric|min:0',
            'denom_100' => 'nullable|numeric|min:0',
            'denom_50' => 'nullable|numeric|min:0',
            'denom_20' => 'nullable|numeric|min:0',
            'denom_10' => 'nullable|numeric|min:0',
            'denom_coins' => 'nullable|numeric|min:0',

            // Summary fields
            'summary_cash_received' => 'nullable|numeric|min:0',
            'summary_net_sale' => 'nullable|numeric|min:0',
            'summary_recovery' => 'nullable|numeric|min:0',
            'summary_credit' => 'nullable|numeric|min:0',
            'summary_expenses' => 'nullable|numeric|min:0',
            'credit_recoveries_total' => 'nullable|numeric|min:0',
            'total_bank_transfers' => 'nullable|numeric|min:0',
            'total_cheques' => 'nullable|numeric|min:0',
        ];
    }
}
