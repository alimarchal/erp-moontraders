<?php

namespace App\Services;

use App\Models\CurrentStockByBatch;
use App\Models\InventoryLedgerEntry;
use App\Models\ProductRecall;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockBatch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductRecallService
{
    public function createRecall(array $data): array
    {
        try {
            DB::beginTransaction();

            $recallNumber = $this->generateRecallNumber();

            $totalQty = 0;
            $totalValue = 0;
            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $item) {
                    $totalQty += $item['quantity_recalled'];
                    $totalValue += $item['quantity_recalled'] * $item['unit_cost'];
                }
            }

            $recall = ProductRecall::create([
                'recall_number' => $recallNumber,
                'recall_date' => $data['recall_date'],
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'],
                'grn_id' => $data['grn_id'] ?? null,
                'recall_type' => $data['recall_type'],
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'total_quantity_recalled' => $totalQty,
                'total_value' => $totalValue,
                'status' => 'draft',
            ]);

            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    $recall->items()->create($itemData);
                }
            }

            DB::commit();

            return [
                'success' => true,
                'data' => $recall->fresh('items'),
                'message' => "Product recall {$recallNumber} created successfully",
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create product recall: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to create product recall: '.$e->getMessage(),
            ];
        }
    }

    public function postRecall(ProductRecall $recall): array
    {
        try {
            DB::beginTransaction();

            if ($recall->status !== 'draft') {
                throw new \Exception('Only draft recalls can be posted');
            }

            $this->validateStockAvailability($recall);

            $adjustmentService = app(StockAdjustmentService::class);
            $adjustment = StockAdjustment::create([
                'adjustment_number' => $adjustmentService->generateAdjustmentNumber(),
                'adjustment_date' => $recall->recall_date,
                'warehouse_id' => $recall->warehouse_id,
                'adjustment_type' => 'recall',
                'product_recall_id' => $recall->id,
                'reason' => $recall->reason,
                'status' => 'draft',
            ]);

            foreach ($recall->items as $recallItem) {
                $currentQty = CurrentStockByBatch::where('stock_batch_id', $recallItem->stock_batch_id)
                    ->where('warehouse_id', $recall->warehouse_id)
                    ->value('quantity_on_hand') ?? 0;

                StockAdjustmentItem::create([
                    'stock_adjustment_id' => $adjustment->id,
                    'product_id' => $recallItem->product_id,
                    'stock_batch_id' => $recallItem->stock_batch_id,
                    'grn_item_id' => $recallItem->grn_item_id,
                    'system_quantity' => $currentQty,
                    'actual_quantity' => 0,
                    'adjustment_quantity' => -$recallItem->quantity_recalled,
                    'unit_cost' => $recallItem->unit_cost,
                    'adjustment_value' => -$recallItem->total_value,
                    'uom_id' => $recallItem->product->stock_uom_id ?? 1,
                ]);
            }

            $result = $adjustmentService->postAdjustment($adjustment);

            if (! $result['success']) {
                DB::rollBack();

                return $result;
            }

            $recall->update([
                'status' => 'posted',
                'stock_adjustment_id' => $adjustment->id,
                'posted_at' => now(),
                'posted_by' => auth()->id(),
            ]);

            DB::commit();

            return [
                'success' => true,
                'data' => $recall->fresh('stockAdjustment'),
                'message' => "Recall {$recall->recall_number} posted successfully",
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to post product recall: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to post product recall: '.$e->getMessage(),
            ];
        }
    }

    protected function validateStockAvailability(ProductRecall $recall): void
    {
        foreach ($recall->items as $item) {
            $batch = StockBatch::find($item->stock_batch_id);
            if (! $batch) {
                throw new \Exception("Stock batch not found for item {$item->id}");
            }

            $availableQty = CurrentStockByBatch::where('stock_batch_id', $item->stock_batch_id)
                ->where('warehouse_id', $recall->warehouse_id)
                ->value('quantity_on_hand') ?? 0;

            if ($item->quantity_recalled > $availableQty) {
                throw new \Exception(
                    "Batch {$batch->batch_code}: Recall qty ({$item->quantity_recalled}) exceeds available ({$availableQty})"
                );
            }

            $issuedQty = InventoryLedgerEntry::where('stock_batch_id', $item->stock_batch_id)
                ->whereNotNull('vehicle_id')
                ->where('transaction_type', 'transfer_in')
                ->sum('debit_qty');

            if ($issuedQty > 0) {
                throw new \Exception(
                    "Batch {$batch->batch_code} has been issued to vans ({$issuedQty} units). Cannot recall from warehouse."
                );
            }

            $soldQty = InventoryLedgerEntry::where('stock_batch_id', $item->stock_batch_id)
                ->where('transaction_type', 'sale')
                ->sum('credit_qty');

            if ($soldQty > 0) {
                throw new \Exception(
                    "Batch {$batch->batch_code} has sales ({$soldQty} units). Cannot recall."
                );
            }
        }
    }

    public function getAvailableBatches(int $supplierId, int $warehouseId, ?array $filters = []): Collection
    {
        $query = StockBatch::where('supplier_id', $supplierId)
            ->where('status', 'active')
            ->with(['product', 'currentStockByBatch' => function ($q) use ($warehouseId) {
                $q->where('warehouse_id', $warehouseId)->where('quantity_on_hand', '>', 0);
            }]);

        if (! empty($filters['batch_code'])) {
            $query->where('batch_code', 'like', "%{$filters['batch_code']}%");
        }

        if (! empty($filters['expiry_from']) && ! empty($filters['expiry_to'])) {
            $query->whereBetween('expiry_date', [$filters['expiry_from'], $filters['expiry_to']]);
        }

        if (! empty($filters['mfg_date'])) {
            $query->whereDate('manufacturing_date', $filters['mfg_date']);
        }

        if (! empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        return $query->get()->filter(function ($batch) {
            return $batch->currentStockByBatch->isNotEmpty() &&
                   $batch->currentStockByBatch->sum('quantity_on_hand') > 0;
        });
    }

    public function generateRecallNumber(): string
    {
        $year = now()->year;
        $prefix = "RCL-{$year}-";

        $lastRecall = ProductRecall::where('recall_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastRecall
            ? (int) substr($lastRecall->recall_number, strlen($prefix)) + 1
            : 1;

        return sprintf('%s%04d', $prefix, $nextNumber);
    }
}
