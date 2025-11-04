<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUomRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'uom_name' => ['required', 'string', 'max:191', 'unique:uoms,uom_name'],
            'symbol' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'must_be_whole_number' => ['sometimes', 'boolean'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'uom_name' => $this->filled('uom_name') ? trim((string) $this->input('uom_name')) : null,
            'symbol' => $this->filled('symbol') ? trim((string) $this->input('symbol')) : null,
            'description' => $this->filled('description') ? trim((string) $this->input('description')) : null,
            'must_be_whole_number' => $this->has('must_be_whole_number') ? $this->boolean('must_be_whole_number') : false,
            'enabled' => $this->has('enabled') ? $this->boolean('enabled') : true,
        ]);
    }
}
