<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BankReconciliationRequest extends FormRequest
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
            'period_from' => ['required', 'date'],
            'period_to' => ['required', 'date', 'after_or_equal:period_from'],
            'opening_balance' => ['required', 'numeric'],
            'statement_balance' => ['required', 'numeric'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
