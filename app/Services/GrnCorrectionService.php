<?php

namespace App\Services;

use App\Models\GoodsReceiptNote;
use App\Models\GrnCorrection;
use App\Models\Product;
use App\Models\StockLedgerEntry;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Fixes a posted GRN line whose conversion factor (or, while its batch is untouched, its product) was entered wrong.
 *
 * The invoice amount of the line stays as it is: the quantity becomes qty_in_purchase_uom × new factor and the unit
 * cost becomes that amount over the new quantity. Nothing posted is overwritten for quantity: the difference is
 * posted as a receipt movement of its own, with stock and inventory ledger rows, dated on the GRN's receipt date so
 * stock on any date since is right. The cost is restated on the GRN's rows and on everything already issued, sold or
 * returned out of the batch, and the cost difference reaches the ledger as one adjusting journal dated today. The old
 * and new figures, the reason and who made the correction are kept in grn_corrections.
 */
class GrnCorrectionService
{
    public const CORRECTION_LABEL = 'GRNC';

    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly InventoryLedgerService $inventoryLedgerService,
        private readonly BatchRecostService $batchRecostService,
        private readonly InventoryGlAdjustmentService $glAdjustmentService,
        private readonly DocumentNumberService $documentNumberService,
    ) {}

    /**
     * @param  array<int, array{id: int|string, product_id: int|string, uom_conversion_factor: float|int|string}>  $items
     * @return array{correction: ?GrnCorrection, cost_jumps: list<string>}
     *
     * @throws RuntimeException when the GRN or a line cannot be corrected
     */
    public function correct(GoodsReceiptNote $grn, array $items, string $reason): array
    {
        return DB::transaction(function () use ($grn, $items, $reason) {
            $grn = GoodsReceiptNote::whereKey($grn->id)->lockForUpdate()->firstOrFail();

            if ($grn->status !== 'posted') {
                throw new RuntimeException("Only a posted GRN can be corrected. {$grn->grn_number} is {$grn->status}.");
            }

            $receiptDate = $grn->receipt_date->toDateString();
            $lines = [];
            $products = [];
            $costJumps = [];
            $cogsDelta = 0.0;
            $recostedMovements = 0;
            $valueDeltas = [];
            $vanValueDelta = 0.0;

            foreach ($items as $formItem) {
                $line = $this->correctLine($grn, $formItem, $receiptDate);

                if ($line === null) {
                    continue;
                }

                $recost = $this->batchRecostService->recostBatch($line['stock_batch_id'], $line['new_unit_cost']);
                $cogsDelta += $recost['cogs_delta'];
                $recostedMovements += $recost['movements'];
                $vanValueDelta += $recost['van_value_delta'];

                foreach ($recost['value_deltas'] as $type => $value) {
                    $valueDeltas[$type] = ($valueDeltas[$type] ?? 0.0) + $value;
                }

                if ($line['old_unit_cost'] > 0 && abs($line['new_unit_cost'] / $line['old_unit_cost'] - 1) > 0.10) {
                    $costJumps[] = sprintf('line %d: %s → %s', $line['line_no'], number_format($line['old_unit_cost'], 2), number_format($line['new_unit_cost'], 2));
                }

                $products[$line['old_product_id']] = true;
                $products[$line['new_product_id']] = true;
                unset($line['line_no']);
                $lines[] = $line;
            }

            if ($lines === []) {
                return ['correction' => null, 'cost_jumps' => []];
            }

            foreach (array_keys($products) as $productId) {
                $this->inventoryService->recalculateStockLedgerBalances($productId, $grn->warehouse_id);
                $this->recalculateInventoryLedgerBalances($productId);
                $this->inventoryService->syncCurrentStockFromValuationLayers($productId, $grn->warehouse_id);
            }

            $grn->update(['total_quantity' => $grn->items()->sum('qty_in_stock_uom')]);

            $correctionNumber = $this->documentNumberService->next('grn_correction', self::CORRECTION_LABEL);

            // What was already issued, sold or returned out of these batches kept its journal at the old cost.
            $journal = $this->glAdjustmentService->postRecostAdjustment(
                $valueDeltas,
                $correctionNumber,
                "{$correctionNumber} on {$grn->grn_number}: stock already issued, sold or returned re-costed",
                vanValueDelta: $vanValueDelta
            );

            $correction = GrnCorrection::create([
                'correction_number' => $correctionNumber,
                'goods_receipt_note_id' => $grn->id,
                'reason' => $reason,
                'journal_entry_id' => $journal?->id,
                'cogs_delta' => round($cogsDelta, 2),
                'recosted_movements' => $recostedMovements,
                'corrected_by' => auth()->id(),
                'corrected_at' => now(),
            ]);
            $correction->lines()->createMany($lines);

            return ['correction' => $correction, 'cost_jumps' => $costJumps];
        });
    }

    /**
     * Correct one GRN line, or return null when nothing on it changed.
     *
     * @param  array{id: int|string, product_id: int|string, uom_conversion_factor: float|int|string}  $formItem
     * @return array<string, mixed>|null
     */
    private function correctLine(GoodsReceiptNote $grn, array $formItem, string $receiptDate): ?array
    {
        $item = DB::table('goods_receipt_note_items')
            ->where('id', $formItem['id'])
            ->where('grn_id', $grn->id)
            ->lockForUpdate()
            ->first();

        if (! $item) {
            throw new RuntimeException('One or more submitted lines do not belong to this GRN.');
        }

        $oldProductId = (int) $item->product_id;
        $newProductId = (int) $formItem['product_id'];
        $oldFactor = (float) $item->uom_conversion_factor;
        $newFactor = (float) $formItem['uom_conversion_factor'];
        $productChanged = $newProductId !== $oldProductId;

        if (! $productChanged && abs($newFactor - $oldFactor) < 0.0001) {
            return null;
        }

        $newProduct = Product::query()
            ->whereKey($newProductId)
            ->where('supplier_id', $grn->supplier_id)
            ->where('is_active', true)
            ->first();

        if (! $newProduct) {
            throw new RuntimeException("Product {$newProductId} is not an active product of this GRN's supplier.");
        }

        $layer = DB::table('stock_valuation_layers')->where('grn_item_id', $item->id)->lockForUpdate()->first();

        if (! $layer) {
            throw new RuntimeException("Line {$item->line_no} has no valuation layer. Check that the GRN was posted properly.");
        }

        $batchId = (int) $layer->stock_batch_id;
        $grnMovements = DB::table('stock_movements')
            ->where('stock_batch_id', $batchId)
            ->where('movement_type', 'grn')
            ->where('reference_type', GoodsReceiptNote::class)
            ->where('reference_id', $grn->id);

        if ($productChanged) {
            $this->assertProductCanChange($item, $newProduct, $layer, (clone $grnMovements)->pluck('id')->all());
        }

        $oldQuantity = (float) $item->quantity_accepted;
        $newQuantity = round((float) $item->qty_in_purchase_uom * $newFactor, 2);
        $lineValue = (float) $item->total_cost;
        $oldUnitCost = (float) $item->unit_cost;

        // The line's value is carried over as it is, so it must already be what the line is worth.
        $impliedValue = (float) $item->qty_in_stock_uom * $oldUnitCost;

        if (abs($impliedValue - $lineValue) > max(1.0, GrnCostDriftService::TOTAL_COST_TOLERANCE_RATIO * abs($lineValue))) {
            throw new RuntimeException(sprintf(
                'Line %d is not consistent: total cost %s but quantity × unit cost is %s. Run "php artisan stock:repair-grn-cost-drift" before correcting it.',
                $item->line_no,
                number_format($lineValue, 2),
                number_format($impliedValue, 2)
            ));
        }

        if ($newQuantity <= 0) {
            throw new RuntimeException("Line {$item->line_no}: the corrected quantity must be more than zero.");
        }

        $newUnitCost = round($lineValue / $newQuantity, 6);
        $delta = round($newQuantity - $oldQuantity, 2);
        $newRemaining = round((float) $layer->quantity_remaining + $delta, 2);

        if ($newRemaining < 0) {
            throw new RuntimeException(sprintf(
                'Line %d: %s of it has already been issued, sold or adjusted, more than the corrected quantity %s.',
                $item->line_no,
                $this->formatQuantity((float) $layer->quantity_received - (float) $layer->quantity_remaining),
                $this->formatQuantity($newQuantity)
            ));
        }

        if ($productChanged) {
            // The batch is untouched, so its only rows are the GRN's own: they move to the right product.
            $movementIds = (clone $grnMovements)->pluck('id')->all();
            DB::table('stock_movements')->whereIn('id', $movementIds)->update(['product_id' => $newProductId]);
            DB::table('stock_ledger_entries')->whereIn('stock_movement_id', $movementIds)->update(['product_id' => $newProductId]);
            DB::table('inventory_ledger_entries')->where('goods_receipt_note_id', $grn->id)->where('stock_batch_id', $batchId)->update(['product_id' => $newProductId]);
            DB::table('stock_batches')->where('id', $batchId)->update(['product_id' => $newProductId]);
            DB::table('current_stock_by_batch')->where('stock_batch_id', $batchId)->update(['product_id' => $newProductId]);
        }

        // The cost of what the GRN received is restated on its own rows; the quantity they posted stays.
        foreach ((clone $grnMovements)->get(['id', 'quantity']) as $movement) {
            DB::table('stock_movements')->where('id', $movement->id)->update([
                'unit_cost' => $newUnitCost,
                'total_value' => round(abs((float) $movement->quantity) * $newUnitCost, 4),
            ]);
            DB::table('stock_ledger_entries')->where('stock_movement_id', $movement->id)->update(['valuation_rate' => $newUnitCost]);
        }

        DB::table('inventory_ledger_entries')
            ->where('goods_receipt_note_id', $grn->id)
            ->where('stock_batch_id', $batchId)
            ->update([
                'unit_cost' => $newUnitCost,
                'total_value' => DB::raw('ROUND((debit_qty + credit_qty) * '.$newUnitCost.', 2)'),
            ]);

        $movementId = abs($delta) >= 0.01
            ? $this->postQuantityDifference($grn, $item, $newProductId, $batchId, $delta, $newUnitCost, $receiptDate, $oldFactor, $newFactor)
            : null;

        DB::table('goods_receipt_note_items')->where('id', $item->id)->update([
            'product_id' => $newProductId,
            'uom_conversion_factor' => $newFactor,
            'qty_in_stock_uom' => $newQuantity,
            'quantity_received' => $newQuantity,
            'quantity_accepted' => $newQuantity,
            'unit_cost' => $newUnitCost,
        ]);

        DB::table('stock_batches')->where('id', $batchId)->update(['unit_cost' => $newUnitCost]);

        DB::table('stock_valuation_layers')->where('id', $layer->id)->update([
            'product_id' => $newProductId,
            'quantity_received' => round((float) $layer->quantity_received + $delta, 2),
            'quantity_remaining' => $newRemaining,
            'unit_cost' => $newUnitCost,
            'value_remaining' => round($newRemaining * $newUnitCost, 4),
        ]);

        $onHand = DB::table('current_stock_by_batch')->where('stock_batch_id', $batchId)->lockForUpdate()->first();

        if ($onHand) {
            $quantityOnHand = round((float) $onHand->quantity_on_hand + $delta, 2);
            DB::table('current_stock_by_batch')->where('id', $onHand->id)->update([
                'quantity_on_hand' => $quantityOnHand,
                'unit_cost' => $newUnitCost,
                'total_value' => round($quantityOnHand * $newUnitCost, 4),
                'last_updated' => now(),
            ]);
        }

        return [
            'line_no' => (int) $item->line_no,
            'goods_receipt_note_item_id' => (int) $item->id,
            'stock_batch_id' => $batchId,
            'old_product_id' => $oldProductId,
            'new_product_id' => $newProductId,
            'old_conversion_factor' => $oldFactor,
            'new_conversion_factor' => $newFactor,
            'old_quantity' => $oldQuantity,
            'new_quantity' => $newQuantity,
            'old_unit_cost' => $oldUnitCost,
            'new_unit_cost' => $newUnitCost,
            'line_value' => $lineValue,
            'stock_movement_id' => $movementId,
        ];
    }

    /**
     * A product can only be swapped while the batch holds nothing but the GRN's own receipt, and only for one that
     * is counted the same way and posts the same journal.
     *
     * @param  list<int>  $grnMovementIds
     */
    private function assertProductCanChange(object $item, Product $newProduct, object $layer, array $grnMovementIds): void
    {
        if ((int) $newProduct->uom_id !== (int) $item->stock_uom_id) {
            throw new RuntimeException("{$newProduct->product_name} must use the same stock unit as line {$item->line_no}.");
        }

        if ((bool) $newProduct->is_powder !== (bool) Product::query()->whereKey($item->product_id)->value('is_powder')) {
            throw new RuntimeException('A powder product cannot be swapped for a non-powder one (or the other way): the posted FMR journal would no longer match.');
        }

        $batchUsed = DB::table('stock_movements')
            ->where('stock_batch_id', $layer->stock_batch_id)
            ->whereNotIn('id', $grnMovementIds)
            ->exists();

        if ($batchUsed || (float) $layer->quantity_remaining < (float) $layer->quantity_received) {
            throw new RuntimeException("The product on line {$item->line_no} cannot be changed: its batch has already been issued or adjusted.");
        }
    }

    /**
     * Post the quantity the correction adds or takes off as a receipt movement of the GRN, with its stock and
     * inventory ledger rows, on the GRN's receipt date. Running balances are rebuilt by the caller.
     */
    private function postQuantityDifference(GoodsReceiptNote $grn, object $item, int $productId, int $batchId, float $delta, float $unitCost, string $date, float $oldFactor, float $newFactor): int
    {
        $movement = StockMovement::create([
            'movement_type' => 'grn',
            'reference_type' => GoodsReceiptNote::class,
            'reference_id' => $grn->id,
            'movement_date' => $date,
            'product_id' => $productId,
            'stock_batch_id' => $batchId,
            'warehouse_id' => $grn->warehouse_id,
            'quantity' => $delta,
            'uom_id' => $item->stock_uom_id,
            'unit_cost' => $unitCost,
            'total_value' => round(abs($delta) * $unitCost, 4),
            'created_by' => auth()->id(),
        ]);

        StockLedgerEntry::create([
            'product_id' => $productId,
            'warehouse_id' => $grn->warehouse_id,
            'stock_batch_id' => $batchId,
            'entry_date' => $date,
            'stock_movement_id' => $movement->id,
            'quantity_in' => max($delta, 0),
            'quantity_out' => max(-$delta, 0),
            'quantity_balance' => 0,
            'valuation_rate' => $unitCost,
            'stock_value' => 0,
            'reference_type' => GoodsReceiptNote::class,
            'reference_id' => $grn->id,
            'created_at' => now(),
        ]);

        $this->inventoryLedgerService->recordPurchaseCorrection(
            productId: $productId,
            warehouseId: $grn->warehouse_id,
            quantity: $delta,
            unitCost: $unitCost,
            grnId: $grn->id,
            date: $date,
            notes: sprintf('GRN correction on %s line %d: conversion factor %s → %s', $grn->grn_number, $item->line_no, $this->formatQuantity($oldFactor), $this->formatQuantity($newFactor)),
            batchId: $batchId,
        );

        return $movement->id;
    }

    private function recalculateInventoryLedgerBalances(int $productId): void
    {
        $balance = 0.0;

        $entries = DB::table('inventory_ledger_entries')
            ->where('product_id', $productId)
            ->orderBy('date')
            ->orderBy('id')
            ->get(['id', 'debit_qty', 'credit_qty']);

        foreach ($entries as $entry) {
            $balance += (float) $entry->debit_qty - (float) $entry->credit_qty;

            DB::table('inventory_ledger_entries')->where('id', $entry->id)->update(['running_balance' => $balance]);
        }
    }

    private function formatQuantity(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 4, '.', ''), '0'), '.');
    }
}
