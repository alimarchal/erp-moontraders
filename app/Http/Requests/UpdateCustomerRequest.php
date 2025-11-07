<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Normalise payload prior to validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'customer_code' => $this->customer_code ? strtoupper(trim((string) $this->customer_code)) : null,
            'customer_name' => $this->customer_name ? trim((string) $this->customer_name) : null,
            'business_name' => $this->business_name ? trim((string) $this->business_name) : null,
            'phone' => $this->phone ? trim((string) $this->phone) : null,
            'email' => $this->email ? strtolower(trim((string) $this->email)) : null,
            'sub_locality' => $this->sub_locality ? trim((string) $this->sub_locality) : null,
            'city' => $this->city ? trim((string) $this->city) : null,
            'state' => $this->state ? trim((string) $this->state) : null,
            'country' => $this->country ? trim((string) $this->country) : null,
            'channel_type' => $this->channel_type ? trim((string) $this->channel_type) : null,
            'customer_category' => $this->customer_category ? trim((string) $this->customer_category) : null,
            'notes' => $this->notes ? trim((string) $this->notes) : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $customerId = optional($this->route('customer'))->id;

        return [
            'customer_code' => ['required', 'string', 'max:191', Rule::unique('customers', 'customer_code')->ignore($customerId)],
            'customer_name' => ['required', 'string', 'max:191'],
            'business_name' => ['nullable', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:191', Rule::unique('customers', 'email')->ignore($customerId)],
            'address' => ['nullable', 'string'],
            'sub_locality' => ['nullable', 'string', 'max:191'],
            'city' => ['nullable', 'string', 'max:191'],
            'state' => ['nullable', 'string', 'max:191'],
            'country' => ['nullable', 'string', 'max:191'],
            'channel_type' => ['required', 'string', Rule::in(Customer::CHANNEL_TYPES)],
            'customer_category' => ['required', 'string', Rule::in(Customer::CUSTOMER_CATEGORIES)],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'payment_terms' => ['nullable', 'integer', 'between:0,365'],
            'credit_used' => ['nullable', 'numeric', 'min:0'],
            'receivable_balance' => ['nullable', 'numeric'],
            'payable_balance' => ['nullable', 'numeric'],
            'lifetime_value' => ['nullable', 'numeric'],
            'receivable_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'payable_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'sales_rep_id' => ['nullable', 'exists:users,id'],
            'last_sale_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
