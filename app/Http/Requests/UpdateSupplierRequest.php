<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends StoreSupplierRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $supplierId = $this->route('supplier')?->id;

        $rules['supplier_name'] = [
            'required',
            'string',
            'max:255',
            Rule::unique('suppliers', 'supplier_name')->ignore($supplierId),
        ];

        return $rules;
    }
}
