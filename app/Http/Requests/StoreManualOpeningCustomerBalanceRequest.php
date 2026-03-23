<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreManualOpeningCustomerBalanceRequest extends FormRequest
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
            'employee_id' => ['required', 'exists:employees,id'],
            'customer_id' => ['required', 'exists:customers,id'],
            'balance_date' => ['required', 'date'],
            'opening_balance' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_id.required' => 'Please select a salesman/employee.',
            'customer_id.required' => 'Please select a customer.',
            'balance_date.required' => 'Please enter the balance date.',
            'opening_balance.required' => 'Please enter the opening balance amount.',
            'opening_balance.min' => 'The opening balance must be at least 0.01.',
        ];
    }
}
