<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BankEntryCategoryRequest extends FormRequest
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
        $category = $this->route('bankEntryCategory');

        return [
            'name' => ['required', 'string', 'max:191', Rule::unique('bank_entry_categories', 'name')->ignore($category)],
            'type' => ['required', Rule::in(['receipt', 'payment', 'both'])],
            'default_chart_of_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
