<?php

namespace App\Http\Requests;

use App\Models\Attachment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBankStatementEntryRequest extends FormRequest
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
            'bank_account_id' => ['required', 'exists:bank_accounts,id'],
            'posting_date' => ['required', 'date', 'before_or_equal:today'],
            'value_date' => ['nullable', 'date'],
            'direction' => ['required', Rule::in(['credit', 'debit'])],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'reference' => ['nullable', 'string', 'max:191'],
            'party_name_in_statement' => ['nullable', 'string', 'max:191'],
            'narration' => ['nullable', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:2000'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'bank_entry_category_id' => ['nullable', 'exists:bank_entry_categories,id'],
            'confirm_duplicate' => ['nullable', 'boolean'],
            'scan_id' => ['nullable', 'exists:bank_receipt_scans,id'],
            'after' => ['nullable', Rule::in(['save', 'another', 'submit'])],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'mimes:'.implode(',', config('bank.attachments.mimes')), 'max:'.config('bank.attachments.max_kb')],
            'label' => ['nullable', Rule::in(array_keys(Attachment::LABELS))],
            'attachment_description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'posting_date.before_or_equal' => 'The date cannot be in the future.',
            'amount.gt' => 'The amount must be more than zero.',
        ];
    }
}
