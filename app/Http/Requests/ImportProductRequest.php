<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'import_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
            'dry_run' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'import_file.required' => 'Please select an Excel file to import.',
            'import_file.mimes' => 'The file must be an Excel file (.xlsx, .xls) or CSV (.csv).',
            'import_file.max' => 'The file must not be larger than 5MB.',
        ];
    }
}
