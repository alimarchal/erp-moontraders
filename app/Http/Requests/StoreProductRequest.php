<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Prepare request payload for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'product_code' => $this->product_code ? strtoupper(trim((string) $this->product_code)) : null,
            'product_name' => $this->product_name ? trim((string) $this->product_name) : null,
            'barcode' => $this->barcode ? trim((string) $this->barcode) : null,
            'brand' => $this->brand ? trim((string) $this->brand) : null,
            'pack_size' => $this->pack_size ? trim((string) $this->pack_size) : null,
            'valuation_method' => $this->valuation_method ? strtoupper(trim((string) $this->valuation_method)) : null,
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
            'product_code' => ['required', 'string', 'max:191', Rule::unique('products', 'product_code')],
            'product_name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:product_categories,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'uom_id' => ['required', 'exists:uoms,id'],
            'sales_uom_id' => ['nullable', 'exists:uoms,id'],
            'uom_conversion_factor' => ['nullable', 'numeric', 'min:0.001', 'max:999999'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'pack_size' => ['nullable', 'string', 'max:120'],
            'barcode' => ['nullable', 'string', 'max:191', Rule::unique('products', 'barcode')],
            'brand' => ['nullable', 'string', 'max:120'],
            'valuation_method' => ['required', Rule::in(Product::VALUATION_METHODS)],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'unit_sell_price' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
