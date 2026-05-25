<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRevenueDetailRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'revenue_category_id' => [
                'required',
                Rule::exists('revenue_categories', 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->when($this->input('supplier_id'), fn ($rule) => $rule->where('supplier_id', $this->input('supplier_id'))),
            ],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'transaction_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
