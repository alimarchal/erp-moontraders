<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $simplified = config('app.expense_simplified_entry');

        return [
            'category' => ['required', 'in:stationary,tcs,tonner_it,salaries,fuel,van_work'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'transaction_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],

            // Fuel-specific
            'vehicle_id' => ['nullable', Rule::requiredIf(fn () => $this->category === 'fuel' && ! $simplified), 'exists:vehicles,id'],
            'liters' => ['nullable', Rule::requiredIf(fn () => $this->category === 'fuel' && ! $simplified), 'numeric', 'min:0.01'],

            // Salaries-specific
            'employee_id' => ['nullable', Rule::requiredIf(fn () => $this->category === 'salaries' && ! $simplified), 'exists:employees,id'],

            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'vehicle_id.required' => 'Vehicle is required for Fuel expenses.',
            'liters.required' => 'Liters is required for Fuel expenses.',
            'employee_id.required' => 'Employee is required for Salary expenses.',
        ];
    }
}
