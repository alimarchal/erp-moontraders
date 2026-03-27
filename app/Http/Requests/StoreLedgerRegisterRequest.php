<?php

namespace App\Http\Requests;

use App\Enums\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLedgerRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => 'required|exists:suppliers,id',
            'transaction_date' => 'required|date',
            'document_type' => ['nullable', Rule::enum(DocumentType::class)],
            'document_number' => 'nullable|string|max:255',
            'sap_code' => 'nullable|string|max:255',
            'online_amount' => 'nullable|numeric|min:0',
            'invoice_amount' => 'nullable|numeric|min:0',
            'expenses_amount' => 'nullable|numeric|min:0',
            'za_point_five_percent_amount' => 'nullable|numeric|min:0',
            'claim_adjust_amount' => 'nullable|numeric|min:0',
            'advance_tax_amount' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:1000',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'supplier_id.required' => 'Please select a supplier.',
            'transaction_date.required' => 'Transaction date is required.',
            'transaction_date.date' => 'Please enter a valid date.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'online_amount' => $this->online_amount ?: 0,
            'invoice_amount' => $this->invoice_amount ?: 0,
            'expenses_amount' => $this->expenses_amount ?: 0,
            'za_point_five_percent_amount' => $this->za_point_five_percent_amount ?: 0,
            'claim_adjust_amount' => $this->claim_adjust_amount ?: 0,
            'advance_tax_amount' => $this->advance_tax_amount ?: 0,
        ]);
    }
}
