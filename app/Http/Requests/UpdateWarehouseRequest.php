<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWarehouseRequest extends FormRequest
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
            'warehouse_name' => ['sometimes', 'required', 'string', 'max:255', 'unique:warehouses,warehouse_name,' . $this->route('warehouse')->id],
            'disabled' => ['sometimes', 'boolean'],
            'is_group' => ['sometimes', 'boolean'],
            'parent_warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'warehouse_type_id' => ['nullable', 'exists:warehouse_types,id'],
            'is_rejected_warehouse' => ['sometimes', 'boolean'],
            'default_in_transit_warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'email_id' => ['nullable', 'email', 'max:255'],
            'phone_no' => ['nullable', 'string', 'max:255'],
            'mobile_no' => ['nullable', 'string', 'max:255'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'pin' => ['nullable', 'string', 'max:255'],
        ];
    }
}
