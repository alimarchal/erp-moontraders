<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseDetailRequest extends FormRequest
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
            'category' => ['required', 'in:stationary,tcs,tonner_it,salaries,fuel,van_work'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'transaction_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],

            // Fuel-specific
            'vehicle_id' => ['nullable', 'required_if:category,fuel', 'exists:vehicles,id'],
            'liters' => ['nullable', 'required_if:category,fuel', 'numeric', 'min:0.01'],

            // Salaries-specific
            'employee_id' => ['nullable', 'required_if:category,salaries', 'exists:employees,id'],

            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'vehicle_id.required_if' => 'Vehicle is required for Fuel expenses.',
            'liters.required_if' => 'Liters is required for Fuel expenses.',
            'employee_id.required_if' => 'Employee is required for Salary expenses.',
        ];
    }
}
