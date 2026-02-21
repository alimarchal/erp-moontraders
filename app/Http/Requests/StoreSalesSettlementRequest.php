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
        $jsonFields = [
            'credit_sales',
            'advance_taxes',
            'amr_powders',
            'amr_liquids',
            'bank_transfers',
            'cheques',
            'recoveries_entries',
            'bank_slips',
            'percentage_expenses',
        ];

        foreach ($jsonFields as $field) {
            if ($this->has($field) && is_string($this->$field)) {
                $this->merge([
                    $field => json_decode($this->$field, true),
                ]);
            }
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

            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.goods_issue_item_id' => 'nullable|exists:goods_issue_items,id',
            'items.*.quantity_issued' => 'required|numeric|min:0',
            'items.*.quantity_sold' => 'required|numeric|min:0',
            'items.*.quantity_returned' => 'nullable|numeric|min:0',
            'items.*.quantity_shortage' => 'nullable|numeric|min:0',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.selling_price' => 'nullable|numeric|min:0',

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

            'credit_sales' => 'nullable|array',
            'credit_sales.*.customer_id' => 'required_with:credit_sales|exists:customers,id',
            'credit_sales.*.invoice_number' => 'nullable|string|max:100',
            'credit_sales.*.sale_amount' => 'required_with:credit_sales|numeric|min:0',
            'credit_sales.*.payment_received' => 'nullable|numeric|min:0',
            'credit_sales.*.previous_balance' => 'nullable|numeric',
            'credit_sales.*.new_balance' => 'nullable|numeric',
            'credit_sales.*.notes' => 'nullable|string',

            'recoveries_entries' => 'nullable|array',
            'recoveries_entries.*.customer_id' => 'required_with:recoveries_entries|exists:customers,id',
            'recoveries_entries.*.recovery_number' => 'nullable|string|max:100',
            'recoveries_entries.*.payment_method' => 'required_with:recoveries_entries|in:cash,bank_transfer',
            'recoveries_entries.*.bank_account_id' => 'required_if:recoveries_entries.*.payment_method,bank_transfer|nullable|exists:bank_accounts,id',
            'recoveries_entries.*.amount' => 'required_with:recoveries_entries|numeric|min:0',
            'recoveries_entries.*.previous_balance' => 'nullable|numeric',
            'recoveries_entries.*.new_balance' => 'nullable|numeric',
            'recoveries_entries.*.notes' => 'nullable|string',

            'advance_taxes' => 'nullable|array',
            'advance_taxes.*.customer_id' => 'required_with:advance_taxes|exists:customers,id',
            'advance_taxes.*.sale_amount' => 'nullable|numeric|min:0',
            'advance_taxes.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'advance_taxes.*.tax_amount' => 'required_with:advance_taxes|numeric|min:0',
            'advance_taxes.*.invoice_number' => 'nullable|string|max:100',
            'advance_taxes.*.notes' => 'nullable|string',

            'amr_powders' => 'nullable|array',
            'amr_powders.*.product_id' => 'required_with:amr_powders|exists:products,id',
            'amr_powders.*.stock_batch_id' => config('app.use_batch_expiry') ? 'required_with:amr_powders|exists:stock_batches,id' : 'nullable|exists:stock_batches,id',
            'amr_powders.*.batch_code' => 'nullable|string|max:100',
            'amr_powders.*.quantity' => 'required_with:amr_powders|numeric|min:0',
            'amr_powders.*.amount' => 'required_with:amr_powders|numeric|min:0',
            'amr_powders.*.notes' => 'nullable|string',

            'amr_liquids' => 'nullable|array',
            'amr_liquids.*.product_id' => 'required_with:amr_liquids|exists:products,id',
            'amr_liquids.*.stock_batch_id' => config('app.use_batch_expiry') ? 'required_with:amr_liquids|exists:stock_batches,id' : 'nullable|exists:stock_batches,id',
            'amr_liquids.*.batch_code' => 'nullable|string|max:100',
            'amr_liquids.*.quantity' => 'required_with:amr_liquids|numeric|min:0',
            'amr_liquids.*.amount' => 'required_with:amr_liquids|numeric|min:0',
            'amr_liquids.*.notes' => 'nullable|string',

            'bank_transfers' => 'nullable|array',
            'bank_transfers.*.bank_account_id' => 'required_with:bank_transfers|exists:bank_accounts,id',
            'bank_transfers.*.customer_id' => 'nullable|exists:customers,id',
            'bank_transfers.*.amount' => 'required_with:bank_transfers|numeric|min:0',
            'bank_transfers.*.reference_number' => 'nullable|string|max:100',

            'bank_slips' => 'nullable|array',
            'bank_slips.*.bank_account_id' => 'required_with:bank_slips|exists:bank_accounts,id',
            'bank_slips.*.amount' => 'required_with:bank_slips|numeric|min:0',
            'bank_slips.*.reference_number' => 'nullable|string|max:100',
            'bank_slips.*.deposit_date' => 'nullable|date',
            'total_bank_slips' => 'nullable|numeric|min:0',

            'cheques' => 'nullable|array',
            'cheques.*.customer_id' => 'nullable|exists:customers,id',
            'cheques.*.amount' => 'required_with:cheques|numeric|min:0',
            'cheques.*.cheque_number' => 'nullable|string|max:100',
            'cheques.*.bank_name' => 'nullable|string|max:100',
            'cheques.*.cheque_date' => 'nullable|date',

            'percentage_expenses' => 'nullable|array',
            'percentage_expenses.*.customer_id' => 'required_with:percentage_expenses|exists:customers,id',
            'percentage_expenses.*.amount' => 'required_with:percentage_expenses|numeric|min:0',
            'percentage_expenses.*.notes' => 'nullable|string',

            'expenses' => 'nullable|array',
            'expenses.*.expense_account_id' => [
                'required_with:expenses',
                'exists:chart_of_accounts,id',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $account = \App\Models\ChartOfAccount::find($value);
                    if ($account && str_starts_with((string) $account->account_code, '511')) {
                        $fail('COGS accounts (511x) cannot be used as settlement expenses — they are posted automatically via inventory.');
                    }
                },
            ],
            'expenses.*.description' => 'nullable|string|max:255',
            'expenses.*.amount' => 'required_with:expenses|numeric|min:0',

            'denom_5000' => 'nullable|numeric|min:0',
            'denom_1000' => 'nullable|numeric|min:0',
            'denom_500' => 'nullable|numeric|min:0',
            'denom_100' => 'nullable|numeric|min:0',
            'denom_50' => 'nullable|numeric|min:0',
            'denom_20' => 'nullable|numeric|min:0',
            'denom_10' => 'nullable|numeric|min:0',
            'denom_coins' => 'nullable|numeric|min:0',

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
