<?php

namespace App\Http\Requests;

use App\Enums\DocumentType;
use App\Models\LedgerRegister;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PostLedgerRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * An entry with an online amount is posted against the active bank account it was paid from.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bank_account_id' => [
                Rule::requiredIf(fn (): bool => $this->paysFromBank()),
                'nullable',
                'integer',
                Rule::exists('bank_accounts', 'id')->where('is_active', true)->whereNotNull('chart_of_account_id'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bank_account_id.required' => 'Choose the bank account the online amount was paid from.',
            'bank_account_id.exists' => 'Choose an active bank account that has a GL account.',
        ];
    }

    private function paysFromBank(): bool
    {
        $entry = $this->route('ledgerRegister');

        return $entry instanceof LedgerRegister
            && $entry->document_type !== DocumentType::Ob
            && (float) $entry->online_amount > 0;
    }
}
