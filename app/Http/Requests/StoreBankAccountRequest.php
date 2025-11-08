<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'account_name' => $this->account_name ? trim((string) $this->account_name) : null,
            'account_number' => $this->account_number ? trim((string) $this->account_number) : null,
            'bank_name' => $this->bank_name ? trim((string) $this->bank_name) : null,
            'branch' => $this->branch ? trim((string) $this->branch) : null,
            'iban' => $this->iban ? strtoupper(trim((string) $this->iban)) : null,
            'swift_code' => $this->swift_code ? strtoupper(trim((string) $this->swift_code)) : null,
            'description' => $this->description ? trim((string) $this->description) : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        return [
            'account_name' => ['required', 'string', 'max:191'],
            'account_number' => ['required', 'string', 'max:191', Rule::unique('bank_accounts', 'account_number')],
            'bank_name' => ['nullable', 'string', 'max:191'],
            'branch' => ['nullable', 'string', 'max:191'],
            'iban' => ['nullable', 'string', 'max:191'],
            'swift_code' => ['nullable', 'string', 'max:191'],
            'chart_of_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string'],
        ];
    }
}
