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

            // only used for entries typed in by hand; imported lines keep what the bank printed
            'posting_date' => ['nullable', 'date', 'before_or_equal:today'],
            'value_date' => ['nullable', 'date'],
            'direction' => ['nullable', 'in:credit,debit'],
            'amount' => ['nullable', 'numeric', 'gt:0', 'max:999999999999.99'],
            'reference' => ['nullable', 'string', 'max:191'],
            'party_name_in_statement' => ['nullable', 'string', 'max:191'],
            'narration' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
