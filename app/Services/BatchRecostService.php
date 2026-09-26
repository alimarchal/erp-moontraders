<?php

namespace App\Services;

use App\Models\StockBatch;
use Illuminate\Support\Facades\DB;

class BatchRecostService
{
    /** Movement types whose value reaches the P&L as cost of goods sold. */
    private const COGS_MOVEMENT_TYPES = ['sale', 'shortage'];

    /** Movement types grouped the way InventoryGlAdjustmentService posts them. */
    private const GL_DELTA_TYPES = [
        'transfer' => 'transfer',
        'sale' => 'sale',
        'return' => 'return',
        'shortage' => 'shortage',
        'adjustment' => 'adjustment',
        'damage' => 'adjustment',
        'theft' => 'adjustment',
    ];

    /**
     * Costs closer than this are treated as equal. It is deliberately a whole paisa rather
     * than a float epsilon: movements record a 2-decimal cost while a receipt cost carries
     * six, so a tighter threshold would rewrite historical documents over sub-paisa rounding
     * instead of over a real cost correction.
     */
    private const COST_TOLERANCE = 0.01;

    /**
     * Re-cost everything already posted out of a batch after its receipt cost changed.
     *
     * A GRN correction restates the batch, its valuation layer and its current stock, but
     * goods issues, settlements and returns posted before that correction still carry the
     * old cost. Left alone they leave a residue in the warehouse value that matches no
     * physical stock, and their COGS stays wrong.
     *
     * Posted journal entries are deliberately not rewritten — the returned `cogs_delta` is
     * what an adjusting journal has to carry, and is the caller's to act on.
     *
     * `value_deltas` is SUM(quantity × (new − old cost)) per movement type, with quantities
     * signed as stored, for InventoryGlAdjustmentService to post.
     *
     * `van_value_delta` is what the van_stock_batches rows' value changed by.
     *
     * @return array{movements: int, ledger_rows: int, issue_items: int, van_rows: int, cogs_delta: float, value_deltas: array<string, float>, van_value_delta: float}
     *
     * @throws \RuntimeException when part of the batch was moved to another product by a batch transfer
     */
    public function recostBatch(
        int $stockBatchId,
        float $newUnitCost,
        ?int $excludeMovementId = null,
        bool $dryRun = false
    ): array {
        $result = ['movements' => 0, 'ledger_rows' => 0, 'issue_items' => 0, 'van_rows' => 0, 'cogs_delta' => 0.0, 'value_deltas' => [], 'van_value_delta' => 0.0];

        $movements = DB::table('stock_movements')
            ->where('stock_batch_id', $stockBatchId)
            ->when($excludeMovementId !== null, fn ($query) => $query->where('id', '!=', $excludeMovementId))
            ->where('movement_type', '!=', 'grn')
            ->get(['id', 'movement_type', 'quantity', 'unit_cost', 'goods_issue_item_id', 'reference_type', 'reference_id']);

        $stale = $movements->filter(
            fn ($movement) => abs((float) $movement->unit_cost - $newUnitCost) > self::COST_TOLERANCE
        );

        if ($stale->isEmpty()) {
            return $result;
        }

        // A partial batch transfer carried units to another product's batch at the old cost.
        // Re-costing only this side would leave the two batches valued apart from the GRN
        // that bought them both.
        $transferred = $stale->first(fn ($movement) => $movement->reference_type === StockBatch::class);

        if ($transferred) {
            throw new \RuntimeException(sprintf(
                'Batch %s was partly transferred to another product (batch %s), so its cost cannot be corrected here.',
                DB::table('stock_batches')->where('id', $stockBatchId)->value('batch_code'),
                DB::table('stock_batches')->where('id', $transferred->reference_id)->value('batch_code')
            ));
        }

        foreach ($stale as $movement) {
            $quantity = abs((float) $movement->quantity);
            $deltaType = self::GL_DELTA_TYPES[$movement->movement_type] ?? 'adjustment';
            $result['value_deltas'][$deltaType] = ($result['value_deltas'][$deltaType] ?? 0.0)
                + (float) $movement->quantity * ($newUnitCost - (float) $movement->unit_cost);

            if (in_array($movement->movement_type, self::COGS_MOVEMENT_TYPES, true)) {
                $result['cogs_delta'] += $quantity * ($newUnitCost - (float) $movement->unit_cost);
            }

            $result['movements']++;

            if ($dryRun) {
                continue;
            }

            DB::table('stock_movements')->where('id', $movement->id)->update([
                'unit_cost' => $newUnitCost,
                'total_value' => round($quantity * $newUnitCost, 4),
            ]);

            // stock_value is left to the caller's balance recalculation, which derives it
            // from valuation_rate and the running quantity balance.
            DB::table('stock_ledger_entries')
                ->where('stock_movement_id', $movement->id)
                ->update(['valuation_rate' => $newUnitCost]);
        }

        $result['cogs_delta'] = round($result['cogs_delta'], 2);
        $result['value_deltas'] = array_map(fn (float $delta) => round($delta, 4), $result['value_deltas']);
        $result['ledger_rows'] = $this->recostInventoryLedger($stockBatchId, $newUnitCost, $dryRun);

        $itemIds = $stale->pluck('goods_issue_item_id')->filter()->unique()->values();
        foreach ($itemIds as $itemId) {
            $weighted = $this->weightedIssueCost((int) $itemId, $stockBatchId, $newUnitCost);

            if ($weighted === null) {
                continue;
            }

            $result['issue_items'] += $this->recostGoodsIssueItem((int) $itemId, $weighted, $dryRun);
            $result['van_rows'] += $this->recostVanStock((int) $itemId, $weighted, $dryRun, $result['van_value_delta']);
        }

        return $result;
    }

    /**
     * The purchase rows belong to the GRN itself and keep the invoice amount, so only the
     * issue/sale/return rows for this batch are restated.
     */
    private function recostInventoryLedger(int $stockBatchId, float $newUnitCost, bool $dryRun): int
    {
        $entries = DB::table('inventory_ledger_entries')
            ->where('stock_batch_id', $stockBatchId)
            ->whereNull('goods_receipt_note_id')
            ->get(['id', 'debit_qty', 'credit_qty', 'unit_cost']);

        $count = 0;

        foreach ($entries as $entry) {
            if (abs((float) $entry->unit_cost - $newUnitCost) <= self::COST_TOLERANCE) {
                continue;
            }

            $count++;

            if ($dryRun) {
                continue;
            }

            $quantity = (float) $entry->debit_qty + (float) $entry->credit_qty;

            DB::table('inventory_ledger_entries')->where('id', $entry->id)->update([
                'unit_cost' => $newUnitCost,
                'total_value' => round($quantity * $newUnitCost, 2),
            ]);
        }

        return $count;
    }

    /**
     * A goods issue line can draw from several batches, so its cost is the quantity-weighted
     * average of the movements it produced — recomputed here from the corrected costs.
     */
    private function recostGoodsIssueItem(int $goodsIssueItemId, float $weighted, bool $dryRun): int
    {
        $current = DB::table('goods_issue_items')->where('id', $goodsIssueItemId)->value('unit_cost');

        if ($current === null || abs((float) $current - $weighted) <= 0.005) {
            return 0;
        }

        if (! $dryRun) {
            DB::table('goods_issue_items')->where('id', $goodsIssueItemId)->update(['unit_cost' => $weighted]);
        }

        return 1;
    }

    /**
     * Van stock holds a 2-decimal cost, so what the re-cost changed on the van is taken from
     * the rows themselves rather than from the movements.
     */
    private function recostVanStock(int $goodsIssueItemId, float $weighted, bool $dryRun, float &$valueDelta): int
    {
        $query = DB::table('van_stock_batches')->where('goods_issue_item_id', $goodsIssueItemId);
        $count = (clone $query)->whereRaw('ABS(unit_cost - ?) > 0.005', [$weighted])->count();
        $valueDelta += (float) (clone $query)->selectRaw('COALESCE(SUM(quantity_on_hand * (? - unit_cost)), 0) as delta', [$weighted])->value('delta');

        if ($count > 0 && ! $dryRun) {
            $query->update(['unit_cost' => $weighted]);
        }

        return $count;
    }

    /**
     * The corrected cost is substituted for this batch's own movements, so a dry run reports
     * the same figures the real run will write.
     */
    private function weightedIssueCost(int $goodsIssueItemId, int $stockBatchId, float $newUnitCost): ?float
    {
        $totals = DB::table('stock_movements')
            ->where('goods_issue_item_id', $goodsIssueItemId)
            ->where('movement_type', 'transfer')
            ->selectRaw(
                'SUM(ABS(quantity) * CASE WHEN stock_batch_id = ? THEN ? ELSE unit_cost END) as value, '
                .'SUM(ABS(quantity)) as quantity',
                [$stockBatchId, $newUnitCost]
            )
            ->first();

        if (! $totals || (float) $totals->quantity <= 0) {
            return null;
        }

        return round((float) $totals->value / (float) $totals->quantity, 2);
    }
}
