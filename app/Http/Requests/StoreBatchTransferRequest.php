<?php

namespace App\Http\Requests;

use App\Models\CurrentStockByBatch;
use App\Models\StockBatch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreBatchTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only super-admin may perform batch transfers
        return auth()->user()?->hasRole('super-admin') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'stock_batch_id' => ['required', 'integer', 'exists:stock_batches,id'],
            'target_product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $batchId = $this->input('stock_batch_id');
            $targetProductId = $this->input('target_product_id');
            $quantity = (float) $this->input('quantity');

            if (! $batchId || ! $targetProductId) {
                return;
            }

            $csb = CurrentStockByBatch::where('stock_batch_id', $batchId)->first();

            if (! $csb) {
                $validator->errors()->add('stock_batch_id', 'This batch has no active stock record.');

                return;
            }

            // Cannot transfer to the same product
            $batch = StockBatch::find($batchId);
            if ($batch && (int) $batch->product_id === (int) $targetProductId) {
                $validator->errors()->add('target_product_id', 'Target product must be different from the current batch product.');
            }

            // Quantity must not exceed available stock
            if ($quantity > (float) $csb->quantity_on_hand) {
                $validator->errors()->add(
                    'quantity',
                    "Cannot transfer {$quantity}. Only {$csb->quantity_on_hand} units are available."
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'stock_batch_id.required' => 'Please select a batch to transfer.',
            'target_product_id.required' => 'Please select a target product.',
            'quantity.required' => 'Transfer quantity is required.',
            'quantity.min' => 'Quantity must be greater than zero.',
            'reason.required' => 'A reason for the transfer is required.',
            'reason.min' => 'Please provide a meaningful reason (at least 5 characters).',
        ];
    }
}
