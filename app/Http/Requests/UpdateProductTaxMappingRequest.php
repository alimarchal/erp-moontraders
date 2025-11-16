<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductTaxMappingRequest extends FormRequest
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
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'tax_code_id' => ['required', 'integer', 'exists:tax_codes,id'],
            'transaction_type' => ['required', 'string', 'in:sales,purchase,both'],
            'is_taxable' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
