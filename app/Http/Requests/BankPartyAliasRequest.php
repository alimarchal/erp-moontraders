<?php

namespace App\Http\Requests;

use App\Models\BankPartyAlias;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BankPartyAliasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('alias_name')) {
            $this->merge(['alias_name' => BankPartyAlias::normalize($this->input('alias_name'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $alias = $this->route('bankPartyAlias');

        return [
            'alias_name' => ['required', 'string', 'max:191', Rule::unique('bank_party_aliases', 'alias_name')->ignore($alias)],
            'canonical_name' => ['required', 'string', 'max:191'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
