<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BankEntryRuleRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:191'],
            'match_field' => ['required', Rule::in(['narration', 'party', 'reference'])],
            'match_type' => ['required', Rule::in(['contains', 'starts_with', 'equals'])],
            'match_value' => ['required', 'string', 'max:191'],
            'direction' => ['required', Rule::in(['any', 'debit', 'credit'])],
            'company_id' => ['nullable', 'exists:suppliers,id'],
            'bank_entry_category_id' => ['required', 'exists:bank_entry_categories,id'],
            'priority' => ['required', 'integer', 'min:1', 'max:65000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
