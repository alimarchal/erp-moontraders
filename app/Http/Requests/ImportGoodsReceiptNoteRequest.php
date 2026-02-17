<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportGoodsReceiptNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => 'required|exists:suppliers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'receipt_date' => 'required|date',
            'supplier_invoice_date' => 'nullable|date',
            'supplier_invoice_number' => 'nullable|string|max:100',
            'import_file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
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
            'supplier_id.required' => 'Please select a supplier.',
            'warehouse_id.required' => 'Please select a warehouse.',
            'receipt_date.required' => 'Please enter a receipt date.',
        ];
    }
}
