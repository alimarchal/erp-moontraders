<?php

namespace App\Services;

use App\Models\CurrentStockByBatch;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\InventoryLedgerEntry;
use App\Models\JournalEntry;
use App\Models\StockMovement;
use App\Models\VanStockBalance;
use App\Models\VanStockBatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cancels a posted goods issue and hands its lines to a new draft.
 *
 * A posted issue is never edited: its stock movements, inventory ledger and journal
 * entries stay as they were, and offsetting entries dated on the issue's own date undo
 * them, so every date nets to zero. The lines are copied into a fresh draft that points
 * back at the reversed issue, so a wrong salesman, van or quantity is put right by
 * editing that draft and posting it.
 */
class GoodsIssueReversalService
{
    public function __construct(
        private AccountingService $accountingService,
        private InventoryLedgerService $inventoryLedgerService,
        private StockValuationService $stockValuation,
        private InventoryService $inventoryService,
    ) {}

    /**
     * @return array{success: bool, message: string, replacement: GoodsIssue|null}
     */
    public function reverse(GoodsIssue $goodsIssue, string $reason): array
    {
        try {
            $replacement = DB::transaction(function () use ($goodsIssue, $reason): GoodsIssue {
                $goodsIssue = GoodsIssue::whereKey($goodsIssue->id)->lockForUpdate()->firstOrFail();

                $refusal = $this->refusal($goodsIssue);

                if ($refusal !== null) {
                    throw new \RuntimeException($refusal);
                }

                $date = $goodsIssue->issue_date->toDateString();
                // Read before the offsetting movements are written, which net them to zero.
                $issuedQuantities = $this->issuedQuantities($goodsIssue);

                $this->reverseJournalEntries($goodsIssue, $date);
                $this->returnStockToWarehouse($goodsIssue, $date);
                $this->reverseInventoryLedger($goodsIssue, $date);
                $this->removeFromVan($goodsIssue, $issuedQuantities);

                $goodsIssue->update([
                    'status' => 'cancelled',
                    'active_vehicle_lock' => null,
                    'reversed_at' => now(),
                    'reversed_by' => auth()->id(),
                    'reversal_reason' => $reason,
                ]);

                return $this->copyToDraft($goodsIssue);
            });
        } catch (\Throwable $e) {
            Log::error('Failed to reverse goods issue', [
                'goods_issue_id' => $goodsIssue->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => "Could not reverse {$goodsIssue->issue_number}: {$e->getMessage()}",
                'replacement' => null,
            ];
        }

        Log::info('Goods issue reversed', [
            'goods_issue' => $goodsIssue->issue_number,
            'replacement' => $replacement->issue_number,
            'reversed_by' => auth()->id(),
            'reason' => $reason,
        ]);

        return [
            'success' => true,
            'message' => "{$goodsIssue->issue_number} has been reversed and its stock returned to the warehouse. "
                ."Draft {$replacement->issue_number} has been created with the same items: correct it and post it.",
            'replacement' => $replacement,
        ];
    }

    /**
     * Why this issue cannot be reversed, or null when it can.
     */
    public function refusal(GoodsIssue $goodsIssue): ?string
    {
        if ($goodsIssue->status !== 'issued') {
            return 'Only a posted (issued) goods issue can be reversed.';
        }

        $settlement = $goodsIssue->settlement()->first();

        if ($settlement) {
            return "Settlement {$settlement->settlement_number} ({$settlement->status}) has been made against it. "
                .'Its stock has been sold or returned, so the settlement must be reverted or deleted first.';
        }

        foreach ($this->issuedQuantities($goodsIssue) as $productId => $issued) {
            $onVan = (float) VanStockBalance::where('vehicle_id', $goodsIssue->vehicle_id)
                ->where('product_id', $productId)
                ->value('quantity_on_hand');

            if ($onVan + StockValuationService::QTY_EPSILON < $issued) {
                $product = DB::table('products')->where('id', $productId)->value('product_name') ?? "product #{$productId}";

                return "The van holds {$this->quantity($onVan)} of {$product} but this issue loaded {$this->quantity($issued)}. "
                    .'Stock that has already left the van cannot be returned by a reversal.';
            }
        }

        return null;
    }

    /**
     * Net quantity this issue moved onto the van, per product, read from its own movements
     * so supplementary lines are included.
     *
     * @return Collection<int, float>
     */
    private function issuedQuantities(GoodsIssue $goodsIssue): Collection
    {
        return $this->standingMovements($goodsIssue)
            ->groupBy('product_id')
            ->map(fn (Collection $rows) => -$rows->sum(fn ($row) => (float) $row->net_quantity));
    }

    /**
     * The issue's transfer movements netted per line and batch.
     *
     * @return Collection<int, object>
     */
    private function standingMovements(GoodsIssue $goodsIssue): Collection
    {
        return StockMovement::where('reference_type', GoodsIssue::class)
            ->where('reference_id', $goodsIssue->id)
            ->where('movement_type', 'transfer')
            ->selectRaw('goods_issue_item_id, product_id, stock_batch_id, warehouse_id, vehicle_id, uom_id,
                MAX(unit_cost) as unit_cost, SUM(quantity) as net_quantity')
            ->groupBy('goods_issue_item_id', 'product_id', 'stock_batch_id', 'warehouse_id', 'vehicle_id', 'uom_id')
            ->get()
            ->filter(fn ($row) => (float) $row->net_quantity < -StockValuationService::QTY_EPSILON)
            ->values();
    }

    /**
     * Offset the main transfer entry and every supplementary (-S1, -S2 ...) one.
     */
    private function reverseJournalEntries(GoodsIssue $goodsIssue, string $date): void
    {
        $entries = JournalEntry::where('status', 'posted')
            ->where(fn ($query) => $query
                ->where('reference', $goodsIssue->issue_number)
                ->orWhere('reference', 'like', $goodsIssue->issue_number.'-S%'))
            ->orderBy('id')
            ->get();

        foreach ($entries as $entry) {
            $result = $this->accountingService->reverseJournalEntry(
                $entry->id,
                "Reversal of Goods Issue {$entry->reference} (vehicle {$goodsIssue->vehicle?->vehicle_number}; salesman {$goodsIssue->employee?->name})",
                $date
            );

            if (! $result['success']) {
                throw new \RuntimeException($result['message']);
            }
        }
    }

    /**
     * Put each batch back where it was taken from, at the cost it left at.
     */
    private function returnStockToWarehouse(GoodsIssue $goodsIssue, string $date): void
    {
        $standing = $this->standingMovements($goodsIssue);

        foreach ($standing as $row) {
            $quantity = -(float) $row->net_quantity;
            $unitCost = (float) $row->unit_cost;
            $value = round($quantity * $unitCost, 4);

            $movement = StockMovement::create([
                'movement_type' => 'transfer',
                'reference_type' => GoodsIssue::class,
                'reference_id' => $goodsIssue->id,
                'goods_issue_item_id' => $row->goods_issue_item_id,
                'movement_date' => $date,
                'product_id' => $row->product_id,
                'stock_batch_id' => $row->stock_batch_id,
                'warehouse_id' => $row->warehouse_id,
                'vehicle_id' => $row->vehicle_id,
                'quantity' => $quantity,
                'uom_id' => $row->uom_id,
                'unit_cost' => $unitCost,
                'total_value' => $value,
                'created_by' => auth()->id(),
            ]);

            $stockByBatch = CurrentStockByBatch::where('stock_batch_id', $row->stock_batch_id)
                ->where('warehouse_id', $row->warehouse_id)
                ->lockForUpdate()
                ->first();

            if ($stockByBatch) {
                $stockByBatch->quantity_on_hand = (float) $stockByBatch->quantity_on_hand + $quantity;
                $stockByBatch->total_value = round((float) ($stockByBatch->total_value ?? 0) + $value, 4);
                $stockByBatch->status = 'active';
                $stockByBatch->last_updated = now();
                $stockByBatch->save();
            } else {
                CurrentStockByBatch::create([
                    'stock_batch_id' => $row->stock_batch_id,
                    'product_id' => $row->product_id,
                    'warehouse_id' => $row->warehouse_id,
                    'quantity_on_hand' => $quantity,
                    'unit_cost' => $unitCost,
                    'total_value' => $value,
                    'status' => 'active',
                    'last_updated' => now(),
                ]);
            }

            $this->stockValuation->restoreBatch((int) $row->stock_batch_id, (int) $row->warehouse_id, $quantity, $unitCost, $movement->id);
        }

        foreach ($standing->pluck('product_id')->unique() as $productId) {
            $this->inventoryService->syncCurrentStockFromValuationLayers((int) $productId, (int) $goodsIssue->warehouse_id);
        }
    }

    private function reverseInventoryLedger(GoodsIssue $goodsIssue, string $date): void
    {
        $entries = InventoryLedgerEntry::where('goods_issue_id', $goodsIssue->id)
            ->whereIn('transaction_type', [InventoryLedgerEntry::TYPE_TRANSFER_OUT, InventoryLedgerEntry::TYPE_TRANSFER_IN])
            ->orderBy('id')
            ->get();

        foreach ($entries as $entry) {
            $this->inventoryLedgerService->recordIssueReversal(
                $entry,
                $date,
                "Reversal of GI {$goodsIssue->issue_number}".($entry->vehicle_id ? ' (Vehicle OUT)' : ' (Warehouse IN)')
            );
        }
    }

    /**
     * @param  Collection<int, float>  $issuedQuantities
     */
    private function removeFromVan(GoodsIssue $goodsIssue, Collection $issuedQuantities): void
    {
        foreach ($issuedQuantities as $productId => $issued) {
            $balance = VanStockBalance::where('vehicle_id', $goodsIssue->vehicle_id)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if ($balance) {
                $balance->quantity_on_hand = max(0, (float) $balance->quantity_on_hand - $issued);
                $balance->last_updated = now();
                $balance->save();
            }
        }

        VanStockBatch::where('vehicle_id', $goodsIssue->vehicle_id)
            ->whereIn('goods_issue_item_id', $goodsIssue->items()->pluck('id'))
            ->update(['quantity_on_hand' => 0]);
    }

    /**
     * A draft with the same header and lines, numbered next in sequence. Lines for the same
     * product are merged into one, so supplementary lines fold into the line they topped up
     * and the edit form's one-row-per-product rule holds.
     */
    private function copyToDraft(GoodsIssue $goodsIssue): GoodsIssue
    {
        $lines = $goodsIssue->items()
            ->orderBy('line_no')
            ->get()
            ->groupBy(fn (GoodsIssueItem $item) => $item->product_id.'|'.$item->uom_id.'|'.(int) $item->exclude_promotional)
            ->values();

        $replacement = GoodsIssue::create([
            'issue_number' => GoodsIssue::nextIssueNumber(),
            'issue_date' => $goodsIssue->issue_date,
            'warehouse_id' => $goodsIssue->warehouse_id,
            'vehicle_id' => $goodsIssue->vehicle_id,
            'employee_id' => $goodsIssue->employee_id,
            'supplier_id' => $goodsIssue->supplier_id,
            'issued_by' => auth()->id(),
            'stock_in_hand_account_id' => $goodsIssue->stock_in_hand_account_id,
            'van_stock_account_id' => $goodsIssue->van_stock_account_id,
            'status' => 'draft',
            'total_quantity' => $goodsIssue->items()->sum('quantity_issued'),
            'total_value' => $goodsIssue->items()->sum('total_value'),
            'notes' => trim("Replaces {$goodsIssue->issue_number}. ".($goodsIssue->notes ?? '')),
            'replaces_goods_issue_id' => $goodsIssue->id,
        ]);

        foreach ($lines as $index => $items) {
            $first = $items->first();
            $quantity = (float) $items->sum('quantity_issued');

            $replacement->items()->create([
                'line_no' => $index + 1,
                'product_id' => $first->product_id,
                'quantity_issued' => $quantity,
                'unit_cost' => $first->unit_cost,
                'selling_price' => $first->selling_price,
                'uom_id' => $first->uom_id,
                'total_value' => round($quantity * (float) $first->selling_price, 2),
                'exclude_promotional' => $first->exclude_promotional,
            ]);
        }

        return $replacement;
    }

    private function quantity(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.');
    }
}
