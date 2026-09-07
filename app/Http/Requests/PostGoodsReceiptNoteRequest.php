<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PostGoodsReceiptNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'current_password:web'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.required' => 'Please enter your password to post this GRN.',
            'password.current_password' => 'The provided password does not match your current password.',
        ];
    }
}
