<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaxCodeRequest extends FormRequest
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
            'tax_code' => ['required', 'string', 'max:20', 'unique:tax_codes,tax_code'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'tax_type' => ['required', 'in:sales_tax,gst,vat,withholding_tax,excise,customs_duty'],
            'calculation_method' => ['required', 'in:percentage,fixed_amount'],
            'tax_payable_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'tax_receivable_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'is_active' => ['boolean'],
            'is_compound' => ['boolean'],
            'included_in_price' => ['boolean'],
        ];
    }
}
