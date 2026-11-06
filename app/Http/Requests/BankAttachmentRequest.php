<?php

namespace App\Http\Requests;

use App\Models\Attachment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BankAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => ['file', 'mimes:'.implode(',', config('bank.attachments.mimes')), 'max:'.config('bank.attachments.max_kb')],
            'description' => ['nullable', 'string', 'max:500'],
            'label' => ['required', Rule::in(array_keys(Attachment::LABELS))],
        ];
    }
}
