<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BankStatementEntryRequest extends FormRequest
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
            'description' => ['nullable', 'string', 'max:2000'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'bank_entry_category_id' => ['nullable', 'exists:bank_entry_categories,id'],
            'needs_review' => ['nullable', 'boolean'],
        ];
    }
}
