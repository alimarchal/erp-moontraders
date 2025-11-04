<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends StoreEmployeeRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $employeeId = $this->route('employee')?->id;

        $rules['employee_code'] = [
            'required',
            'string',
            'max:20',
            'regex:/^[A-Z0-9\\-_.]+$/',
            Rule::unique('employees', 'employee_code')->ignore($employeeId),
        ];

        $rules['email'] = [
            'nullable',
            'email',
            'max:255',
            Rule::unique('employees', 'email')->ignore($employeeId),
        ];

        return $rules;
    }
}
