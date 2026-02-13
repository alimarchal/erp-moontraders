<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClaimRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'transaction_date' => ['required', 'date'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'claim_month' => ['nullable', 'string', 'max:255'],
            'date_of_dispatch' => ['nullable', 'date'],
            'transaction_type' => ['required', 'in:claim,recovery'],
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:Pending,PartialAdjust,Adjusted'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
