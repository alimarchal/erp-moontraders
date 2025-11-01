<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChartOfAccountRequest extends FormRequest
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
        $chartOfAccountId = $this->route('chart_of_account')?->id ?? $this->route('chart_of_account');

        $parentRules = [
            'nullable',
            'integer',
            'exists:chart_of_accounts,id',
        ];

        if ($chartOfAccountId) {
            $parentRules[] = Rule::notIn([$chartOfAccountId]);
        }

        return [
            'account_type_id' => ['required', 'integer', 'exists:account_types,id'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'parent_id' => $parentRules,
            'account_code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('chart_of_accounts', 'account_code')->ignore($chartOfAccountId),
            ],
            'account_name' => ['required', 'string', 'max:255'],
            'normal_balance' => ['required', 'in:debit,credit'],
            'description' => ['nullable', 'string'],
            'is_group' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Custom attribute names for validation errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'account_type_id' => 'account type',
            'currency_id' => 'currency',
            'parent_id' => 'parent account',
            'account_code' => 'account code',
            'account_name' => 'account name',
            'normal_balance' => 'normal balance',
            'is_group' => 'group flag',
            'is_active' => 'active status',
        ];
    }
}
