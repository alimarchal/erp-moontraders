<?php

namespace App\Services;

use App\Models\SalesSettlement;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Finds and repairs GRN lines whose cost is not the cost the stock was received and issued at.
 *
 * A GRN line's `unit_cost` is what the stock is worth. The receipt movement, the batch, the
 * valuation layer, current stock by batch, the inventory ledger and everything later posted
 * out of the batch (goods issues, settlements, returns) must all carry that same cost. A
 * correction made to a posted GRN used to restate only some of those places, which left the
 * Daily Stock Register, the daily snapshots and the profit reports each valuing the stock
 * differently.
 *
 * Batches a stock adjustment has deliberately revalued are left alone: they are carried at
 * that cost on purpose.
 */
class GrnCostDriftService
{
    /** Costs closer than a whole paisa are the same cost: movements store two decimals, receipts six. */
    public const COST_TOLERANCE = 0.01;

    /** A line whose total_cost misses quantity × unit_cost by more than this share is stale. */
    public const TOTAL_COST_TOLERANCE_RATIO = 0.001;

    public const ROUND_OFF = '5271';

    public const STOCK_IN_HAND = '1151';

    /** How far a repair may move Stock In Hand or Van Stock away from the stock value, in rupees. */
    public const LEDGER_GAP_TOLERANCE = 10.0;

    public function __construct(
        private BatchRecostService $batchRecostService,
        private InventoryGlAdjustmentService $glAdjustmentService,
    ) {}

    /**
     * @return Collection<int, object{grn_id: int, grn_number: string, grn_item_id: int, product_id: int, stock_batch_id: int, grn_movement_id: int, quantity: float, expected_cost: float, movement_cost: float, batch_cost: float, total_cost_gap: float, downstream_movements: int, problems: array<int, string>}>
     */
    public function detect(?int $grnId = null): Collection
    {
        $rows = DB::table('goods_receipt_note_items as item')
            ->join('goods_receipt_notes as grn', 'grn.id', '=', 'item.grn_id')
            ->join('stock_valuation_layers as layer', 'layer.grn_item_id', '=', 'item.id')
            ->join('stock_movements as movement', 'movement.id', '=', 'layer.stock_movement_id')
            ->join('stock_batches as batch', 'batch.id', '=', 'layer.stock_batch_id')
            ->where('grn.status', 'posted')
            ->whereNull('grn.deleted_at')
            ->where('movement.movement_type', 'grn')
            ->when($grnId, fn ($query) => $query->where('grn.id', $grnId))
            ->whereNotIn('batch.id', StockMovement::query()->revaluations()->whereNotNull('stock_batch_id')->select('stock_batch_id'))
            ->select([
                'grn.id as grn_id',
                'grn.grn_number',
                'item.id as grn_item_id',
                'item.product_id',
                'item.qty_in_stock_uom',
                'item.unit_cost as expected_cost',
                'item.total_cost',
                'batch.id as stock_batch_id',
                'batch.unit_cost as batch_cost',
                'movement.id as grn_movement_id',
                'movement.quantity',
                'movement.unit_cost as movement_cost',
            ])
            ->orderBy('grn.id')
            ->orderBy('item.id')
            ->get();

        $downstream = $this->downstreamDriftByBatch($rows->pluck('stock_batch_id')->unique()->all());

        return $rows
            ->map(function (object $row) use ($downstream): object {
                $expected = (float) $row->expected_cost;
                $totalCostGap = round((float) $row->qty_in_stock_uom * $expected - (float) $row->total_cost, 2);
                $problems = [];

                if (abs($totalCostGap) > max(1.0, self::TOTAL_COST_TOLERANCE_RATIO * abs((float) $row->total_cost))) {
                    $problems[] = 'item_total_cost';
                }

                if (abs((float) $row->movement_cost - $expected) > self::COST_TOLERANCE) {
                    $problems[] = 'receipt_movement';
                }

                if (abs((float) $row->batch_cost - round($expected, 2)) > self::COST_TOLERANCE) {
                    $problems[] = 'batch_cost';
                }

                $driftedMovements = (int) ($downstream[$row->stock_batch_id] ?? 0);

                if ($driftedMovements > 0) {
                    $problems[] = 'issued_sold_returned';
                }

                return (object) [
                    'grn_id' => (int) $row->grn_id,
                    'grn_number' => $row->grn_number,
                    'grn_item_id' => (int) $row->grn_item_id,
                    'product_id' => (int) $row->product_id,
                    'stock_batch_id' => (int) $row->stock_batch_id,
                    'grn_movement_id' => (int) $row->grn_movement_id,
                    'quantity' => (float) $row->quantity,
                    'expected_cost' => $expected,
                    'movement_cost' => (float) $row->movement_cost,
                    'batch_cost' => (float) $row->batch_cost,
                    'total_cost_gap' => $totalCostGap,
                    'downstream_movements' => $driftedMovements,
                    'problems' => $problems,
                ];
            })
            ->filter(fn (object $row) => $row->problems !== [])
            ->values();
    }

    /**
     * Bring everything on one drifted GRN line back onto its unit cost.
     *
     * The general ledger is only touched when that keeps Stock In Hand and Van Stock equal to the
     * stock: the re-cost journal is tried first (documents posted at the old cost), and when it
     * would move the gap between ledger and stock it is left out, because those documents were
     * then already posted at the right cost. A repair that cannot keep the ledger in step is
     * rolled back.
     *
     * @return array{movements: int, ledger_rows: int, settlements: int, cogs_delta: float, notes: array<int, string>}
     *
     * @throws \RuntimeException when the batch was partly transferred to another product, or the
     *                           ledger would drift
     */
    public function repair(object $drift, bool $dryRun = false): array
    {
        if ($dryRun) {
            return $this->applyRepair($drift, true, true);
        }

        try {
            return DB::transaction(fn () => $this->applyRepair($drift, false, true));
        } catch (LedgerDriftException) {
            $result = DB::transaction(fn () => $this->applyRepair($drift, false, false));
            $result['notes'][] = 'Re-cost journal skipped: the ledger already agreed with the stock, so documents posted from this batch were not rewritten.';

            return $result;
        }
    }

    /**
     * @return array{movements: int, ledger_rows: int, settlements: int, cogs_delta: float, notes: array<int, string>}
     */
    private function applyRepair(object $drift, bool $dryRun, bool $postRecostJournal): array
    {
        $expected = $drift->expected_cost;
        $batchCost = round($expected, 2);
        $result = ['movements' => 0, 'ledger_rows' => 0, 'settlements' => 0, 'cogs_delta' => 0.0, 'notes' => []];
        $gapsBefore = $dryRun ? [] : $this->glAdjustmentService->stockLedgerGaps();

        $staleTotal = in_array('item_total_cost', $drift->problems, true);
        $itemTotalGap = $drift->total_cost_gap;

        if ($staleTotal && ! $dryRun) {
            $item = DB::table('goods_receipt_note_items')->where('id', $drift->grn_item_id)->first(['qty_in_stock_uom', 'unit_cost']);

            DB::table('goods_receipt_note_items')->where('id', $drift->grn_item_id)->update([
                'total_cost' => round((float) $item->qty_in_stock_uom * (float) $item->unit_cost, 4),
            ]);
        }

        if (! $dryRun) {
            $this->restateReceipt($drift, $expected, $batchCost);
        }

        $recost = $this->batchRecostService->recostBatch($drift->stock_batch_id, $batchCost, $drift->grn_movement_id, $dryRun);

        $result['movements'] = $recost['movements'];
        $result['ledger_rows'] = $recost['ledger_rows'];
        $result['cogs_delta'] = $recost['cogs_delta'];

        if ($dryRun) {
            return $result;
        }

        // Settlements follow the ledger: their lines and headers are restated only together with
        // the journal that carries the same cost, so a profit report and the books never disagree.
        if ($staleTotal || $postRecostJournal) {
            $result['settlements'] = $this->restateSettlementCogs($drift->stock_batch_id);
        }

        if ($staleTotal) {
            $note = $this->reclassifyRoundOff($drift, $itemTotalGap, $recost['cogs_delta']);

            if ($note !== null) {
                $result['notes'][] = $note;
            }
        } elseif ($postRecostJournal) {
            $this->glAdjustmentService->postRecostAdjustment(
                $recost['value_deltas'],
                "RECOST-{$drift->grn_number}-ITEM{$drift->grn_item_id}-".now()->format('YmdHis'),
                "Cost correction on {$drift->grn_number}: stock already issued, sold or returned re-costed",
                vanValueDelta: $recost['van_value_delta']
            );
        }

        $this->guardLedgerStaysInStep($gapsBefore, $postRecostJournal || $staleTotal);

        return $result;
    }

    /**
     * @param  array<string, array{ledger: float, stock: float, gap: float}>  $gapsBefore
     *
     * @throws LedgerDriftException when the repair moved the ledger away from the stock and a
     *                              different treatment is still to be tried
     * @throws \RuntimeException when no treatment is left
     */
    private function guardLedgerStaysInStep(array $gapsBefore, bool $canRetryWithoutJournal): void
    {
        $gapsAfter = $this->glAdjustmentService->stockLedgerGaps();

        foreach ($gapsAfter as $code => $after) {
            $moved = abs($after['gap'] - $gapsBefore[$code]['gap']);

            if ($moved <= self::LEDGER_GAP_TOLERANCE) {
                continue;
            }

            if ($canRetryWithoutJournal) {
                throw new LedgerDriftException("Account {$code} would move {$moved} away from the stock.");
            }

            throw new \RuntimeException("The repair moves account {$code} {$moved} away from the stock value; not applied.");
        }
    }

    /**
     * A line whose total_cost described an earlier quantity was booked short and the missing
     * value went to Round Off. Move it to where it belongs: cost of goods sold for what has
     * been sold since, Stock In Hand for the rest. Only when Round Off holds that amount.
     */
    private function reclassifyRoundOff(object $drift, float $gap, float $cogsDelta): ?string
    {
        $grn = DB::table('goods_receipt_notes')->where('id', $drift->grn_id)->first(['journal_entry_id', 'receipt_date']);

        $roundOff = $grn->journal_entry_id
            ? (float) DB::table('journal_entry_details as line')
                ->join('chart_of_accounts as account', 'account.id', '=', 'line.chart_of_account_id')
                ->where('line.journal_entry_id', $grn->journal_entry_id)
                ->where('account.account_code', self::ROUND_OFF)
                ->selectRaw('COALESCE(SUM(line.debit - line.credit), 0) as balance')
                ->value('balance')
            : 0.0;

        if ($gap <= 0 || abs($roundOff - $gap) > 1.0) {
            return sprintf(
                'Line total_cost restated by %s, but Round Off on %s holds %s, so no journal entry was posted — review the ledger by hand.',
                number_format($gap, 2),
                $drift->grn_number,
                number_format($roundOff, 2)
            );
        }

        $roundOff = round($roundOff, 2);
        $cogsPart = round(max(min($cogsDelta, $roundOff), 0.0), 2);
        $stockPart = round($roundOff - $cogsPart, 2);

        $accounts = DB::table('chart_of_accounts')
            ->whereIn('account_code', [self::STOCK_IN_HAND, self::ROUND_OFF, InventoryGlAdjustmentService::COST_OF_GOODS_SOLD])
            ->pluck('id', 'account_code');
        $costCenterId = DB::table('cost_centers')->where('code', 'CC006')->value('id');

        $lines = [];

        if ($cogsPart >= 0.01) {
            $lines[] = ['account_id' => $accounts[InventoryGlAdjustmentService::COST_OF_GOODS_SOLD], 'debit' => $cogsPart, 'credit' => 0, 'description' => 'Cost of the units already sold, at the corrected cost', 'cost_center_id' => $costCenterId];
        }

        if ($stockPart >= 0.01) {
            $lines[] = ['account_id' => $accounts[self::STOCK_IN_HAND], 'debit' => $stockPart, 'credit' => 0, 'description' => 'Stock value missed on the GRN line', 'cost_center_id' => $costCenterId];
        }

        $lines[] = ['account_id' => $accounts[self::ROUND_OFF], 'debit' => 0, 'credit' => $roundOff, 'description' => 'Reverse Round Off plug on the GRN', 'cost_center_id' => $costCenterId];

        $result = app(AccountingService::class)->createJournalEntry([
            'entry_date' => now()->toDateString(),
            'reference' => "{$drift->grn_number}-ROUNDOFF-FIX-{$drift->grn_item_id}",
            'description' => "{$drift->grn_number}: line booked at a stale total cost; the value that went to Round Off moved to cost of goods sold and Stock In Hand",
            'lines' => $lines,
            'auto_post' => true,
        ]);

        if (! $result['success']) {
            throw new \RuntimeException("Could not post the Round Off correction for {$drift->grn_number}: {$result['message']}");
        }

        return null;
    }

    /**
     * Movements posted out of a batch at a cost other than the one its GRN line says.
     *
     * @param  array<int, int>  $batchIds
     * @return array<int, int> batch id => number of movements
     */
    private function downstreamDriftByBatch(array $batchIds): array
    {
        if ($batchIds === []) {
            return [];
        }

        $counts = [];

        foreach (array_chunk($batchIds, 500) as $chunk) {
            DB::table('stock_movements as movement')
                ->join('stock_valuation_layers as layer', 'layer.stock_batch_id', '=', 'movement.stock_batch_id')
                ->join('goods_receipt_note_items as item', 'item.id', '=', 'layer.grn_item_id')
                ->whereIn('movement.stock_batch_id', $chunk)
                ->where('movement.movement_type', '!=', 'grn')
                ->where('movement.quantity', '!=', 0)
                ->select('movement.stock_batch_id', 'movement.unit_cost', 'item.unit_cost as expected_cost')
                ->get()
                ->each(function (object $row) use (&$counts): void {
                    if (abs((float) $row->unit_cost - round((float) $row->expected_cost, 2)) > self::COST_TOLERANCE) {
                        $counts[$row->stock_batch_id] = ($counts[$row->stock_batch_id] ?? 0) + 1;
                    }
                });
        }

        return $counts;
    }

    /**
     * Receipt movement, batch, valuation layers, current stock by batch and the purchase row
     * of the inventory ledger all take the GRN line's cost.
     */
    private function restateReceipt(object $drift, float $expected, float $batchCost): void
    {
        DB::table('stock_movements')->where('id', $drift->grn_movement_id)->update([
            'unit_cost' => $expected,
            'total_value' => round($drift->quantity * $expected, 4),
        ]);

        DB::table('stock_ledger_entries')
            ->where('stock_movement_id', $drift->grn_movement_id)
            ->update(['valuation_rate' => $expected]);

        DB::table('stock_batches')->where('id', $drift->stock_batch_id)->update(['unit_cost' => $batchCost]);

        DB::table('stock_valuation_layers')
            ->where('stock_batch_id', $drift->stock_batch_id)
            ->update([
                'unit_cost' => $expected,
                'value_remaining' => DB::raw('ROUND(quantity_remaining * '.number_format($expected, 6, '.', '').', 4)'),
            ]);

        DB::table('current_stock_by_batch')
            ->where('stock_batch_id', $drift->stock_batch_id)
            ->update([
                'unit_cost' => $expected,
                'total_value' => DB::raw('ROUND(quantity_on_hand * '.number_format($expected, 6, '.', '').', 4)'),
            ]);

        DB::table('inventory_ledger_entries')
            ->where('goods_receipt_note_id', $drift->grn_id)
            ->where('stock_batch_id', $drift->stock_batch_id)
            ->update([
                'unit_cost' => $expected,
                'total_value' => DB::raw('ROUND(debit_qty * '.number_format($expected, 6, '.', '').', 2)'),
            ]);
    }

    /**
     * A settlement prices what it sold from the movements it produced. After those movements
     * are re-costed, restate the settlement line and its header so the profit reports follow.
     *
     * @return int settlements restated
     */
    private function restateSettlementCogs(int $stockBatchId): int
    {
        $settlementIds = DB::table('stock_movements')
            ->where('stock_batch_id', $stockBatchId)
            ->where('movement_type', 'sale')
            ->where('reference_type', SalesSettlement::class)
            ->distinct()
            ->pluck('reference_id');

        $restated = [];

        foreach ($settlementIds as $settlementId) {
            $productId = DB::table('stock_movements')
                ->where('stock_batch_id', $stockBatchId)
                ->where('movement_type', 'sale')
                ->where('reference_type', SalesSettlement::class)
                ->where('reference_id', $settlementId)
                ->value('product_id');

            $items = DB::table('sales_settlement_items')
                ->where('sales_settlement_id', $settlementId)
                ->where('product_id', $productId)
                ->get(['id', 'total_cogs']);

            if ($items->count() !== 1) {
                continue;
            }

            $sold = DB::table('stock_movements')
                ->where('movement_type', 'sale')
                ->where('reference_type', SalesSettlement::class)
                ->where('reference_id', $settlementId)
                ->where('product_id', $productId)
                ->selectRaw('SUM(ABS(quantity) * unit_cost) as value, SUM(ABS(quantity)) as quantity')
                ->first();

            if (! $sold || (float) $sold->quantity <= 0) {
                continue;
            }

            $newCogs = round((float) $sold->value, 4);
            $delta = round($newCogs - (float) $items[0]->total_cogs, 4);

            if (abs($delta) < 0.005) {
                continue;
            }

            DB::table('sales_settlement_items')->where('id', $items[0]->id)->update([
                'unit_cost' => round((float) $sold->value / (float) $sold->quantity, 6),
                'total_cogs' => $newCogs,
            ]);

            DB::table('sales_settlements')->where('id', $settlementId)->update([
                'total_cogs' => DB::raw('total_cogs + '.number_format($delta, 4, '.', '')),
                'gross_profit' => DB::raw('gross_profit - '.number_format($delta, 4, '.', '')),
            ]);

            $restated[$settlementId] = true;
        }

        return count($restated);
    }
}
