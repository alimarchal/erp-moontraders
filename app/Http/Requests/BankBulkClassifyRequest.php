<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BankBulkClassifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', 'exists:bank_statement_entries,id'],
            'company_id' => ['nullable', 'exists:suppliers,id'],
            'bank_entry_category_id' => ['nullable', 'exists:bank_entry_categories,id'],
            'remember_alias' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['ids.required' => 'Tick at least one entry first.'];
    }
}
