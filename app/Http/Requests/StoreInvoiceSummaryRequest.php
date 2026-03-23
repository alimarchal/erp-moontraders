<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'invoice_date' => ['required', 'date'],
            'invoice_number' => ['required', 'string', 'max:255'],
            'cartons' => ['nullable', 'integer', 'min:0'],
            'invoice_value' => ['nullable', 'numeric', 'min:0'],
            'za_on_invoices' => ['nullable', 'numeric', 'min:0'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'fmr_allowance' => ['nullable', 'numeric', 'min:0'],
            'discount_before_sales_tax' => ['nullable', 'numeric', 'min:0'],
            'excise_duty' => ['nullable', 'numeric', 'min:0'],
            'sales_tax_value' => ['nullable', 'numeric', 'min:0'],
            'advance_tax' => ['nullable', 'numeric', 'min:0'],
            'total_value_with_tax' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $defaults = [
            'cartons' => 0,
            'invoice_value' => 0,
            'za_on_invoices' => 0,
            'discount_value' => 0,
            'fmr_allowance' => 0,
            'discount_before_sales_tax' => 0,
            'excise_duty' => 0,
            'sales_tax_value' => 0,
            'advance_tax' => 0,
            'total_value_with_tax' => 0,
        ];

        foreach ($defaults as $field => $default) {
            if (! $this->filled($field)) {
                $this->merge([$field => $default]);
            }
        }
    }
}
