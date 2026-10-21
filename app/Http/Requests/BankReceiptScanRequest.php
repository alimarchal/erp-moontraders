<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BankReceiptScanRequest extends FormRequest
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
            'files' => ['required', 'array', 'min:1', 'max:30'],
            'files.*' => ['file', 'mimes:'.implode(',', config('bank.attachments.mimes')), 'max:'.config('bank.attachments.max_kb')],
            'bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
            'scan_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['nullable', 'numeric', 'gt:0', 'max:999999999999.99'],
            'direction' => ['nullable', 'in:credit,debit'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['files.required' => 'Choose at least one scan or photo.'];
    }
}
