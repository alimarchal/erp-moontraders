<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BankStatementImportRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480'],
            'opening_balance' => ['nullable', 'numeric'],
            'closing_balance' => ['nullable', 'numeric'],
            'control_total_debit' => ['nullable', 'numeric', 'min:0'],
            'control_total_credit' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
