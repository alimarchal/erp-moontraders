<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BankChequeRequest extends FormRequest
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
        $cheque = $this->route('bankCheque');

        return [
            'bank_account_id' => ['required', 'exists:bank_accounts,id'],
            'cheque_no' => ['required', 'string', 'max:50', Rule::unique('bank_cheques', 'cheque_no')
                ->where('bank_account_id', $this->input('bank_account_id'))
                ->where('direction', $this->input('direction'))
                ->ignore($cheque)],
            'direction' => ['required', Rule::in(['issued', 'received'])],
            'cheque_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'payee_name' => ['nullable', 'string', 'max:191'],
            'status' => ['required', Rule::in(['pending', 'cleared', 'bounced', 'cancelled', 'stale'])],
            'cleared_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
