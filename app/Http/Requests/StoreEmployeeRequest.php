<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
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
            'employee_code' => $this->employee_code ? strtoupper(trim((string) $this->employee_code)) : null,
            'name' => $this->name ? trim((string) $this->name) : null,
            'company_name' => $this->company_name ? trim((string) $this->company_name) : null,
            'designation' => $this->designation ? trim((string) $this->designation) : null,
            'phone' => $this->phone ? trim((string) $this->phone) : null,
            'email' => $this->email ? trim((string) $this->email) : null,
            'address' => $this->address ? trim((string) $this->address) : null,
            'warehouse_id' => $this->filled('warehouse_id') ? (int) $this->warehouse_id : null,
            'user_id' => $this->filled('user_id') ? (int) $this->user_id : null,
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
            'employee_code' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Z0-9\\-_.]+$/',
                Rule::unique('employees', 'employee_code'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('employees', 'email')],
            'address' => ['nullable', 'string'],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'user_id' => ['nullable', 'exists:users,id'],
            'hire_date' => ['nullable', 'date'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Provide normalised validated payload.
     *
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);
        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);

        return $validated;
    }
}
