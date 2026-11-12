<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\CurrentStockByBatch;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\GoodsIssueItemRemoval;
use App\Models\JournalEntry;
use App\Models\StockMovement;
use App\Models\VanStockBalance;
use App\Models\VanStockBatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Takes a quantity back from one line of an issued goods issue before it is settled: the stock returns to the
 * warehouse and the batches it left from (the batch issued last comes back first, at the cost it left at), and
 * every record the issue wrote is offset for that quantity only — stock movements, batch and current stock,
 * valuation layers, inventory ledger, van stock, a `GI-…-R{n}` journal (Dr Stock in Hand / Cr Van Stock) — with
 * an audit row. All entries carry the issue date, as supplementary posting and reversal do.
 */
class GoodsIssueLineRemovalService
{
    public function __construct(
        private AccountingService $accountingService,
        private InventoryLedgerService $inventoryLedgerService,
        private StockValuationService $stockValuation,
        private InventoryService $inventoryService,
    ) {}

    /**
     * Why this quantity cannot be removed from the line, or null when it can.
     */
    public function refusal(GoodsIssueItem $item, float $quantity): ?string
    {
        if ($quantity <= StockValuationService::QTY_EPSILON) {
            return 'The quantity to remove must be more than zero.';
        }

        $lineRefusal = $this->lineRefusal($item);

        if ($lineRefusal !== null) {
            return $lineRefusal;
        }

        $goodsIssue = $item->goodsIssue;
        $standing = (float) $this->standingBatches($item)->sum('quantity');

        if ($quantity > $standing + StockValuationService::QTY_EPSILON) {
            return "Only {$this->quantity($standing)} is left on this line.";
        }

        if ((float) $goodsIssue->items()->sum('quantity_issued') - $quantity <= StockValuationService::QTY_EPSILON) {
            return "This is all that is left on {$goodsIssue->issue_number}. Use Reverse & Re-issue to take back the whole issue.";
        }

        $onVan = (float) VanStockBalance::where('vehicle_id', $goodsIssue->vehicle_id)
            ->where('product_id', $item->product_id)
            ->value('quantity_on_hand');

        if ($onVan + StockValuationService::QTY_EPSILON < $quantity) {
            $product = $item->product->product_name ?? "product #{$item->product_id}";

            return "The van holds {$this->quantity($onVan)} of {$product}; {$this->quantity($quantity)} cannot be taken off it.";
        }

        return null;
    }

    /**
     * Why nothing can be removed from this line at all (issue state, settlement, reversed journal, empty line),
     * or null when some quantity can be — the page uses it to enable the line's Remove button.
     */
    public function lineRefusal(GoodsIssueItem $item): ?string
    {
        $goodsIssue = $item->goodsIssue;

        if ($goodsIssue->status !== 'issued') {
            return 'Only a posted (issued) goods issue can have items removed.';
        }

        $settlement = $goodsIssue->settlement()->first();

        if ($settlement) {
            return "Settlement {$settlement->settlement_number} ({$settlement->status}) has been made against it. "
                .'Revert or delete the settlement first.';
        }

        if ((float) $this->standingBatches($item)->sum('quantity') <= StockValuationService::QTY_EPSILON) {
            return 'Nothing is left on this line.';
        }

        $reversed = $this->postedJournalEntries($goodsIssue)
            ->map(fn (JournalEntry $entry) => JournalEntry::where('status', 'posted')
                ->where(fn ($query) => $query->where('reverses_entry_id', $entry->id)->orWhere('reference', 'REV-'.$entry->reference))
                ->first())
            ->filter()
            ->first();

        if ($reversed) {
            return "Its journal entry has already been reversed by entry #{$reversed->id} ({$reversed->reference}). Ask accounts to look at that entry first.";
        }

        return null;
    }

    /**
     * What is still on the van from this line, per batch, net of earlier removals; the batch issued last first.
     *
     * @return Collection<int, array{stock_batch_id: int, batch_code: string, quantity: float, unit_cost: float, selling_price: float}>
     */
    public function standingBatches(GoodsIssueItem $item): Collection
    {
        return StockMovement::query()
            ->join('stock_batches as sb', 'sb.id', '=', 'stock_movements.stock_batch_id')
            ->where('stock_movements.reference_type', GoodsIssue::class)
            ->where('stock_movements.reference_id', $item->goods_issue_id)
            ->where('stock_movements.goods_issue_item_id', $item->id)
            ->where('stock_movements.movement_type', 'transfer')
            ->groupBy('stock_movements.stock_batch_id', 'sb.batch_code', 'sb.selling_price')
            ->selectRaw('stock_movements.stock_batch_id, sb.batch_code, sb.selling_price,
                SUM(-stock_movements.quantity) as net_quantity,
                MAX(CASE WHEN stock_movements.quantity < 0 THEN stock_movements.unit_cost END) as issued_cost,
                MAX(CASE WHEN stock_movements.quantity < 0 THEN stock_movements.id END) as issued_last')
            ->orderByDesc('issued_last')
            ->get()
            ->filter(fn ($row) => (float) $row->net_quantity > StockValuationService::QTY_EPSILON)
            ->map(fn ($row) => [
                'stock_batch_id' => (int) $row->stock_batch_id,
                'batch_code' => (string) $row->batch_code,
                'quantity' => round((float) $row->net_quantity, 3),
                'unit_cost' => (float) $row->issued_cost,
                'selling_price' => (float) $row->selling_price,
            ])
            ->values();
    }

    /**
     * The batches a removal of this quantity would bring back, and their cost.
     *
     * @return array{batches: list<array{stock_batch_id: int, batch_code: string, quantity: float, unit_cost: float}>, cost: float, selling_value: float}
     */
    public function preview(GoodsIssueItem $item, float $quantity): array
    {
        $left = $quantity;
        $batches = [];
        $cost = 0.0;
        $sellingValue = 0.0;

        foreach ($this->standingBatches($item) as $batch) {
            if ($left <= StockValuationService::QTY_EPSILON) {
                break;
            }

            $take = round(min($left, $batch['quantity']), 3);
            $left -= $take;
            $cost += $take * $batch['unit_cost'];
            $sellingValue += $take * $batch['selling_price'];
            $batches[] = [
                'stock_batch_id' => $batch['stock_batch_id'],
                'batch_code' => $batch['batch_code'],
                'quantity' => $take,
                'unit_cost' => $batch['unit_cost'],
            ];
        }

        return ['batches' => $batches, 'cost' => round($cost, 2), 'selling_value' => round($sellingValue, 2)];
    }

    /**
     * Take the quantity back from the line.
     *
     * @return array{success: bool, message: string, removal: ?GoodsIssueItemRemoval}
     */
    public function remove(GoodsIssueItem $item, float $quantity, string $reason): array
    {
        try {
            $removal = DB::transaction(function () use ($item, $quantity, $reason): GoodsIssueItemRemoval {
                $goodsIssue = GoodsIssue::whereKey($item->goods_issue_id)->lockForUpdate()->firstOrFail();
                $item = GoodsIssueItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
                $item->setRelation('goodsIssue', $goodsIssue);

                $refusal = $this->refusal($item, $quantity);

                if ($refusal !== null) {
                    throw new \RuntimeException($refusal);
                }

                $date = $goodsIssue->issue_date->toDateString();
                $preview = $this->preview($item, $quantity);
                $notes = "Removed from {$goodsIssue->issue_number}: {$reason}";

                foreach ($preview['batches'] as $batch) {
                    $this->returnBatch($goodsIssue, $item, $batch, $date, $notes);
                }

                $this->inventoryService->syncCurrentStockFromValuationLayers((int) $item->product_id, (int) $goodsIssue->warehouse_id);
                $this->takeOffVan($goodsIssue, $item, $quantity, $preview['cost']);
                $journal = $this->postJournal($goodsIssue, $preview['cost'], $reason);

                $item->update([
                    'quantity_issued' => max(0, round((float) $item->quantity_issued - $quantity, 3)),
                    'total_value' => max(0, round((float) $item->total_value - $preview['selling_value'], 2)),
                ]);

                $goodsIssue->update([
                    'total_quantity' => $goodsIssue->items()->sum('quantity_issued'),
                    'total_value' => $goodsIssue->items()->sum('total_value'),
                ]);

                return GoodsIssueItemRemoval::create([
                    'goods_issue_id' => $goodsIssue->id,
                    'goods_issue_item_id' => $item->id,
                    'quantity' => $quantity,
                    'cost_value' => $preview['cost'],
                    'selling_value' => $preview['selling_value'],
                    'reason' => $reason,
                    'journal_entry_id' => $journal?->id,
                    'batches' => $preview['batches'],
                    'removed_by' => auth()->id(),
                    'removed_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            Log::warning('Goods issue line removal refused or failed', [
                'goods_issue_id' => $item->goods_issue_id,
                'goods_issue_item_id' => $item->id,
                'quantity' => $quantity,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage(), 'removal' => null];
        }

        $removal->load(['goodsIssue.warehouse', 'item.product', 'journalEntry']);

        return [
            'success' => true,
            'message' => "Removed {$this->quantity($quantity)} of {$removal->item->product->product_name} from {$removal->goodsIssue->issue_number}. "
                ."Stock is back in {$removal->goodsIssue->warehouse->warehouse_name}"
                .($removal->journalEntry ? "; journal {$removal->journalEntry->reference} posted." : '.'),
            'removal' => $removal,
        ];
    }

    /**
     * @param  array{stock_batch_id: int, batch_code: string, quantity: float, unit_cost: float}  $batch
     */
    private function returnBatch(GoodsIssue $goodsIssue, GoodsIssueItem $item, array $batch, string $date, string $notes): void
    {
        $quantity = $batch['quantity'];
        $value = round($quantity * $batch['unit_cost'], 4);

        $movement = StockMovement::create([
            'movement_type' => 'transfer',
            'reference_type' => GoodsIssue::class,
            'reference_id' => $goodsIssue->id,
            'goods_issue_item_id' => $item->id,
            'movement_date' => $date,
            'product_id' => $item->product_id,
            'stock_batch_id' => $batch['stock_batch_id'],
            'warehouse_id' => $goodsIssue->warehouse_id,
            'vehicle_id' => $goodsIssue->vehicle_id,
            'quantity' => $quantity,
            'uom_id' => $item->uom_id,
            'unit_cost' => $batch['unit_cost'],
            'total_value' => $value,
            'created_by' => auth()->id(),
        ]);

        $stockByBatch = CurrentStockByBatch::where('stock_batch_id', $batch['stock_batch_id'])
            ->where('warehouse_id', $goodsIssue->warehouse_id)
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
                'stock_batch_id' => $batch['stock_batch_id'],
                'product_id' => $item->product_id,
                'warehouse_id' => $goodsIssue->warehouse_id,
                'quantity_on_hand' => $quantity,
                'unit_cost' => $batch['unit_cost'],
                'total_value' => $value,
                'status' => 'active',
                'last_updated' => now(),
            ]);
        }

        $this->stockValuation->restoreBatch($batch['stock_batch_id'], (int) $goodsIssue->warehouse_id, $quantity, $batch['unit_cost'], $movement->id);

        $this->inventoryLedgerService->recordIssueRemoval(
            (int) $item->product_id,
            (int) $goodsIssue->warehouse_id,
            (int) $goodsIssue->vehicle_id,
            $goodsIssue->employee_id,
            $quantity,
            $batch['unit_cost'],
            $goodsIssue->id,
            $date,
            "{$notes} - Batch {$batch['batch_code']}",
            $batch['stock_batch_id']
        );
    }

    /**
     * Reduce the van's balance and the line's van batch; the van's average cost keeps its value equal to 1155.
     */
    private function takeOffVan(GoodsIssue $goodsIssue, GoodsIssueItem $item, float $quantity, float $cost): void
    {
        $balance = VanStockBalance::where('vehicle_id', $goodsIssue->vehicle_id)
            ->where('product_id', $item->product_id)
            ->lockForUpdate()
            ->first();

        if ($balance) {
            $held = (float) $balance->quantity_on_hand;
            $left = max(0.0, $held - $quantity);
            $balance->average_cost = $left > StockValuationService::QTY_EPSILON
                ? max(0.0, ($held * (float) $balance->average_cost - $cost) / $left)
                : 0;
            $balance->quantity_on_hand = $left;
            $balance->last_updated = now();
            $balance->save();
        }

        $vanBatch = VanStockBatch::where('vehicle_id', $goodsIssue->vehicle_id)
            ->where('goods_issue_item_id', $item->id)
            ->lockForUpdate()
            ->first();

        if ($vanBatch) {
            $vanBatch->update(['quantity_on_hand' => max(0, round((float) $vanBatch->quantity_on_hand - $quantity, 3))]);
        }
    }

    /**
     * `GI-…-R{n}`: Dr Stock in Hand / Cr Van Stock for the cost of what came back, dated the issue date.
     */
    private function postJournal(GoodsIssue $goodsIssue, float $cost, string $reason): ?JournalEntry
    {
        if ($cost <= 0) {
            return null;
        }

        $goodsIssue->loadMissing(['stockInHandAccount', 'vanStockAccount', 'employee', 'vehicle']);
        $stockInHand = $goodsIssue->stockInHandAccount ?: ChartOfAccount::where('account_code', '1151')->first();
        $vanStock = $goodsIssue->vanStockAccount ?: ChartOfAccount::where('account_code', '1155')->first();

        if (! $stockInHand || ! $vanStock) {
            throw new \RuntimeException('Goods Issue is missing GL accounts. Please set Stock In Hand and Van Stock accounts.');
        }

        $sequence = JournalEntry::where('reference', 'like', $goodsIssue->issue_number.'-R%')
            ->pluck('reference')
            ->filter(fn (string $reference) => $goodsIssue->ownsJournalReference($reference))
            ->count() + 1;
        $reference = "{$goodsIssue->issue_number}-R{$sequence}";
        $costCenterId = $goodsIssue->employee?->cost_center_id;
        $vehicle = $goodsIssue->vehicle->vehicle_number ?? 'N/A';
        $salesman = $goodsIssue->employee->name ?? 'N/A';

        $result = $this->accountingService->createJournalEntry([
            'entry_date' => $goodsIssue->issue_date,
            'reference' => $reference,
            'description' => "Items removed from Goods Issue {$goodsIssue->issue_number} back to the warehouse (vehicle {$vehicle}; salesman {$salesman}): {$reason}",
            'lines' => [
                [
                    'line_no' => 1,
                    'account_id' => $stockInHand->id,
                    'debit' => $cost,
                    'credit' => 0,
                    'description' => 'Stock returned to the warehouse from the van',
                    'cost_center_id' => $costCenterId,
                ],
                [
                    'line_no' => 2,
                    'account_id' => $vanStock->id,
                    'debit' => 0,
                    'credit' => $cost,
                    'description' => "Off van {$vehicle}",
                    'cost_center_id' => $costCenterId,
                ],
            ],
            'auto_post' => true,
        ]);

        if (! $result['success']) {
            throw new \RuntimeException($result['message'] ?? 'Could not post the removal journal entry.');
        }

        return $result['data'];
    }

    /**
     * The issue's own posted entries: main, supplementary (-S) and removal (-R).
     *
     * @return Collection<int, JournalEntry>
     */
    private function postedJournalEntries(GoodsIssue $goodsIssue): Collection
    {
        return JournalEntry::where('status', 'posted')
            ->where('reference', 'like', $goodsIssue->issue_number.'%')
            ->orderBy('id')
            ->get()
            ->filter(fn (JournalEntry $entry) => $goodsIssue->ownsJournalReference($entry->reference))
            ->values();
    }

    private function quantity(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.');
    }
}
