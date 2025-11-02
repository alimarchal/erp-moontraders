<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateCostCenterRequest extends StoreCostCenterRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $costCenterId = $this->route('cost_center')?->id;

        $rules['code'] = [
            'required',
            'string',
            'max:20',
            'regex:/^[A-Z0-9\-_]+$/i',
            Rule::unique('cost_centers', 'code')->ignore($costCenterId),
        ];

        $rules['parent_id'] = [
            'nullable',
            'exists:cost_centers,id',
            Rule::notIn([$costCenterId]),
        ];

        return $rules;
    }
}
