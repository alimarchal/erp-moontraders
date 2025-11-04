<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVehicleRequest extends FormRequest
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
            'vehicle_number' => ['required', 'string', 'max:191', 'unique:vehicles,vehicle_number'],
            'registration_number' => ['required', 'string', 'max:191', 'unique:vehicles,registration_number'],
            'vehicle_type' => ['nullable', 'string', 'max:100'],
            'make_model' => ['nullable', 'string', 'max:191'],
            'year' => ['nullable', 'string', 'max:4'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'employee_id' => ['nullable', 'exists:employees,id'],
            'driver_name' => ['nullable', 'string', 'max:191'],
            'driver_phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'vehicle_number' => $this->filled('vehicle_number') ? strtoupper(trim((string) $this->input('vehicle_number'))) : null,
            'registration_number' => $this->filled('registration_number') ? strtoupper(trim((string) $this->input('registration_number'))) : null,
            'vehicle_type' => $this->filled('vehicle_type') ? trim((string) $this->input('vehicle_type')) : null,
            'make_model' => $this->filled('make_model') ? trim((string) $this->input('make_model')) : null,
            'year' => $this->filled('year') ? trim((string) $this->input('year')) : null,
            'company_id' => $this->filled('company_id') ? (int) $this->input('company_id') : null,
            'supplier_id' => $this->filled('supplier_id') ? (int) $this->input('supplier_id') : null,
            'employee_id' => $this->filled('employee_id') ? (int) $this->input('employee_id') : null,
            'driver_name' => $this->filled('driver_name') ? trim((string) $this->input('driver_name')) : null,
            'driver_phone' => $this->filled('driver_phone') ? preg_replace('/\\s+/', ' ', trim((string) $this->input('driver_phone'))) : null,
        ]);
    }
}
