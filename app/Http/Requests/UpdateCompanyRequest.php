<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $companyId = $this->route('company')?->id;

        return [
            'company_name' => ['required', 'string', 'max:255', 'unique:companies,company_name,'.$companyId],
            'abbr' => ['nullable', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'max:191'],
            'tax_id' => ['nullable', 'string', 'max:191'],
            'domain' => ['nullable', 'string', 'max:191'],
            'phone_no' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:191'],
            'fax' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url', 'max:191'],
            'company_logo' => ['nullable', 'string'],
            'company_description' => ['nullable', 'string'],
            'registration_details' => ['nullable', 'string'],
            'date_of_establishment' => ['nullable', 'date'],
            'date_of_incorporation' => ['nullable', 'date', 'after_or_equal:date_of_establishment'],
            'date_of_commencement' => ['nullable', 'date'],
            'parent_company_id' => ['nullable', 'exists:companies,id', 'not_in:'.$companyId],
            'is_group' => ['boolean'],
            'lft' => ['nullable', 'integer', 'min:0'],
            'rgt' => ['nullable', 'integer', 'min:0'],
            'default_currency_id' => ['nullable', 'exists:currencies,id'],
            'cost_center_id' => ['nullable', 'exists:cost_centers,id'],
            'default_bank_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'default_cash_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'default_receivable_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'default_payable_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'default_expense_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'default_income_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'write_off_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'round_off_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'enable_perpetual_inventory' => ['boolean'],
            'default_inventory_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'stock_adjustment_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'allow_account_creation_against_child_company' => ['boolean'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'monthly_sales_target' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_group' => $this->boolean('is_group'),
            'enable_perpetual_inventory' => $this->boolean('enable_perpetual_inventory', true),
            'allow_account_creation_against_child_company' => $this->boolean('allow_account_creation_against_child_company'),
        ]);
    }
}
