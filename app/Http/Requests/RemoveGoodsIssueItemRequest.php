<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Taking a quantity back from an issued goods issue line: how much, why, and the user's password.
 */
class RemoveGoodsIssueItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('goods-issue-remove-items');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity.gt' => 'Enter a quantity above zero.',
            'quantity.decimal' => 'Use at most two decimals.',
            'reason.min' => 'Give a reason of at least 5 characters.',
        ];
    }
}
