<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'category_code' => $this->category_code ? strtoupper(trim((string) $this->category_code)) : null,
            'category_name' => $this->category_name ? trim((string) $this->category_name) : null,
            'description' => $this->description ? trim((string) $this->description) : null,
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
        return [
            'category_code' => ['required', 'string', 'max:191', Rule::unique('product_categories', 'category_code')],
            'category_name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'exists:product_categories,id'],
            'default_inventory_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'default_cogs_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'default_sales_revenue_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
