<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProfitCategoryDetailRequest extends FormRequest
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
            'profit_category_id' => [
                'required',
                Rule::exists('profit_categories', 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->when($this->input('supplier_id'), fn ($rule) => $rule->where('supplier_id', $this->input('supplier_id'))),
            ],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'transaction_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
