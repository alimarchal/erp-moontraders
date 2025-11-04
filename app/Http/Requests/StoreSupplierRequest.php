<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'supplier_name' => $this->supplier_name ? trim((string) $this->supplier_name) : null,
            'short_name' => $this->short_name ? trim((string) $this->short_name) : null,
            'supplier_group' => $this->supplier_group ? trim((string) $this->supplier_group) : null,
            'supplier_type' => $this->supplier_type ? trim((string) $this->supplier_type) : null,
            'default_price_list' => $this->default_price_list ? trim((string) $this->default_price_list) : null,
            'print_language' => $this->print_language ? trim((string) $this->print_language) : null,
            'tax_id' => $this->tax_id ? trim((string) $this->tax_id) : null,
            'pan_number' => $this->pan_number ? trim((string) $this->pan_number) : null,
            'is_transporter' => $this->boolean('is_transporter'),
            'is_internal_supplier' => $this->boolean('is_internal_supplier'),
            'disabled' => $this->boolean('disabled'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supplier_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('suppliers', 'supplier_name'),
            ],
            'short_name' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:120'],
            'supplier_group' => ['nullable', 'string', 'max:120'],
            'supplier_type' => ['nullable', 'string', 'max:120'],
            'is_transporter' => ['boolean'],
            'is_internal_supplier' => ['boolean'],
            'disabled' => ['boolean'],
            'default_currency_id' => ['nullable', 'exists:currencies,id'],
            'default_bank_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'default_price_list' => ['nullable', 'string', 'max:255'],
            'supplier_details' => ['nullable', 'string'],
            'website' => ['nullable', 'string', 'max:255', 'url'],
            'print_language' => ['nullable', 'string', 'max:100'],
            'supplier_primary_address' => ['nullable', 'string'],
            'supplier_primary_contact' => ['nullable', 'string'],
            'tax_id' => ['nullable', 'string', 'max:120'],
            'pan_number' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * Provide normalised validated payload.
     *
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        $validated['is_transporter'] = (bool) ($validated['is_transporter'] ?? false);
        $validated['is_internal_supplier'] = (bool) ($validated['is_internal_supplier'] ?? false);
        $validated['disabled'] = (bool) ($validated['disabled'] ?? false);

        return $validated;
    }
}
