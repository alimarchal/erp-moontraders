<?php

namespace App\Console\Commands\Stock;

use App\Models\StockValuationLayer;
use App\Notifications\InventoryConsistencyMismatch;
use App\Services\InventoryService;
use App\Services\StockValuationService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class VerifyInventoryConsistency extends Command
{
    /**
     * The movement types that move warehouse stock. Same list the snapshot rebuild uses:
     * 'sale' and 'shortage' carry a warehouse_id for reference but belong to the van side.
     */
    private const WAREHOUSE_MOVEMENT_TYPES = ['grn', 'transfer', 'adjustment', 'return', 'damage', 'theft'];

    /** Quantities below this are treated as equal. */
    private const QTY_EPSILON = 0.001;

    protected $signature = 'inventory:verify-consistency
        {--supplier_id= : Only check products of this supplier}
        {--fix : Re-align stock_valuation_layers and current_stock with current_stock_by_batch}';

    protected $description = 'Check that the four places warehouse stock is recorded still agree: the '
        .'stock_movements ledger, current_stock_by_batch, stock_valuation_layers and current_stock. '
        .'Reports every disagreement and emails the backup recipient; --fix repairs what can be derived.';

    public function __construct(
        private StockValuationService $stockValuation,
        private InventoryService $inventoryService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $supplierId = $this->option('supplier_id');

        $productQuery = DB::table('products')->whereNull('deleted_at');
        if ($supplierId) {
            $productQuery->where('supplier_id', $supplierId);
        }
        $productIds = $productQuery->pluck('id');

        if ($productIds->isEmpty()) {
            $this->warn('No matching products found — nothing to check.');

            return self::SUCCESS;
        }

        if ($this->option('fix')) {
            $merged = $this->mergeSplitBatchLayers($productIds);

            if ($merged > 0) {
                $this->info("Merged {$merged} batch(es) back onto a single valuation layer.");
            }

            $repaired = $this->repairValuationLayers($productIds);

            if ($repaired === 0 && $merged === 0) {
                $this->info('No valuation layer needed repairing.');
            } elseif ($repaired > 0) {
                $this->info("Re-aligned {$repaired} batch(es) of valuation layers with current stock.");
            }
        }

        $batchDrift = $this->batchesOutOfStep($productIds);
        $productDrift = $this->productsOutOfStep($productIds);
        $this->reportSplitBatches($productIds);

        if ($batchDrift === [] && $productDrift === []) {
            $this->info(sprintf(
                'Ledger, current_stock_by_batch, stock_valuation_layers and current_stock agree for all %d product(s)%s.',
                $productIds->count(),
                $supplierId ? " (supplier_id={$supplierId})" : ''
            ));

            return self::SUCCESS;
        }

        $this->reportProductDrift($productDrift);
        $this->reportBatchDrift($batchDrift);

        Log::warning('Inventory consistency check found disagreements', [
            'products' => $productDrift,
            'batches' => $batchDrift,
        ]);

        $this->emailMismatches($productDrift, $batchDrift, $supplierId);

        $this->error('Inventory records do not agree. Ledger differences need investigating; the rest can be repaired with --fix.');

        return self::FAILURE;
    }

    /**
     * Batches carrying more than one valuation layer.
     *
     * Not a disagreement — the quantities still add up — so this only prints.
     * It is worth seeing because such a batch is listed twice on the Goods Issue
     * batch picker, and --fix folds it back into one layer.
     */
    private function reportSplitBatches(Collection $productIds): void
    {
        $split = DB::table('stock_valuation_layers')
            ->whereIn('product_id', $productIds)
            ->where('quantity_remaining', '>', 0)
            ->selectRaw('product_id, stock_batch_id, COUNT(*) as layer_count')
            ->groupBy('product_id', 'stock_batch_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($split->isEmpty()) {
            return;
        }

        $this->warn($split->count().' batch(es) hold more than one valuation layer (run --fix to fold them into one):');
        $this->table(
            ['Batch', 'Product', 'Layers'],
            $split->map(fn ($row) => [$row->stock_batch_id, $row->product_id, $row->layer_count])->all()
        );
    }

    /**
     * Fold a batch's extra valuation layers back into the one the GRN created.
     *
     * A count surplus used to be written as a second layer for the same batch,
     * and that layer carried no grn_item_id. The Goods Issue batch picker joins
     * goods_receipt_note_items, so its quantity was counted as available yet
     * never offered for issue, and a goods issue could not consume it. Merging
     * the quantity into the GRN layer leaves the batch looking exactly as a
     * clean receipt does.
     *
     * Quantity is conserved, so current_stock and the ledger are unaffected.
     */
    private function mergeSplitBatchLayers(Collection $productIds): int
    {
        $split = DB::table('stock_valuation_layers')
            ->whereIn('product_id', $productIds)
            ->selectRaw('stock_batch_id, warehouse_id, COUNT(*) as layer_count')
            ->groupBy('stock_batch_id', 'warehouse_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $merged = 0;

        foreach ($split as $batch) {
            $layers = StockValuationLayer::where('stock_batch_id', $batch->stock_batch_id)
                ->where('warehouse_id', $batch->warehouse_id)
                ->orderByRaw('grn_item_id IS NULL')
                ->orderBy('receipt_date')
                ->orderBy('id')
                ->get();

            $keep = $layers->shift();

            if ($keep === null || $keep->grn_item_id === null) {
                // Nothing to merge into that the batch picker can offer; leave it
                // for the report rather than guess.
                continue;
            }

            DB::transaction(function () use ($keep, $layers) {
                foreach ($layers as $layer) {
                    $keep->quantity_received = (float) $keep->quantity_received + (float) $layer->quantity_received;
                    $keep->quantity_remaining = (float) $keep->quantity_remaining + (float) $layer->quantity_remaining;
                    $layer->delete();
                }

                $keep->value_remaining = round((float) $keep->quantity_remaining * (float) $keep->unit_cost, 4);
                $keep->is_depleted = (float) $keep->quantity_remaining <= self::QTY_EPSILON;
                $keep->save();
            });

            $this->line(sprintf(
                '  batch %d (warehouse %d): %d layers → 1, quantity %s',
                $batch->stock_batch_id,
                $batch->warehouse_id,
                $batch->layer_count,
                number_format((float) $keep->quantity_remaining, 3)
            ));

            $merged++;
        }

        return $merged;
    }

    /**
     * Bring each batch's valuation layers back to the quantity current_stock_by_batch
     * holds for it, then resync current_stock for the products touched.
     *
     * Only stock_valuation_layers and current_stock are written. stock_movements and
     * current_stock_by_batch are left exactly as they are: a ledger disagreement is a
     * posting problem, not something this command may paper over.
     */
    private function repairValuationLayers(Collection $productIds): int
    {
        $drift = $this->batchesOutOfStep($productIds);

        if ($drift === []) {
            return 0;
        }

        $affectedPairs = [];
        $repaired = 0;

        foreach ($drift as $row) {
            DB::transaction(function () use ($row) {
                $delta = $row['stock'] - $row['layers'];

                if ($delta < 0) {
                    $this->stockValuation->consumeBatch($row['batch_id'], $row['warehouse_id'], abs($delta));
                } else {
                    $this->stockValuation->restoreBatch(
                        $row['batch_id'],
                        $row['warehouse_id'],
                        $delta,
                        $row['unit_cost']
                    );
                }
            });

            $affectedPairs[$row['product_id'].'_'.$row['warehouse_id']] = [$row['product_id'], $row['warehouse_id']];
            $repaired++;

            $this->line(sprintf(
                '  batch %d (product %d, warehouse %d): layers %s → %s',
                $row['batch_id'],
                $row['product_id'],
                $row['warehouse_id'],
                number_format($row['layers'], 3),
                number_format($row['stock'], 3)
            ));
        }

        foreach ($affectedPairs as [$productId, $warehouseId]) {
            DB::transaction(fn () => $this->inventoryService->syncCurrentStockFromValuationLayers($productId, $warehouseId));
        }

        return $repaired;
    }

    /**
     * Per batch: what current_stock_by_batch holds against what the valuation layers hold.
     *
     * @return array<int, array{batch_id: int, product_id: int, warehouse_id: int, unit_cost: float, stock: float, layers: float}>
     */
    private function batchesOutOfStep(Collection $productIds): array
    {
        $stock = DB::table('current_stock_by_batch')
            ->whereIn('product_id', $productIds)
            ->selectRaw('stock_batch_id, product_id, warehouse_id, unit_cost, SUM(quantity_on_hand) as quantity')
            ->groupBy('stock_batch_id', 'product_id', 'warehouse_id', 'unit_cost')
            ->get();

        $layers = DB::table('stock_valuation_layers')
            ->whereIn('product_id', $productIds)
            ->selectRaw('stock_batch_id, warehouse_id, SUM(quantity_remaining) as quantity')
            ->groupBy('stock_batch_id', 'warehouse_id')
            ->get()
            ->keyBy(fn ($row) => $row->stock_batch_id.'_'.$row->warehouse_id);

        $drift = [];
        $seen = [];

        foreach ($stock as $row) {
            $key = $row->stock_batch_id.'_'.$row->warehouse_id;
            $seen[$key] = true;

            $stockQuantity = round((float) $row->quantity, 3);
            $layerQuantity = round((float) ($layers[$key]->quantity ?? 0), 3);

            if (abs($stockQuantity - $layerQuantity) > self::QTY_EPSILON) {
                $drift[] = [
                    'batch_id' => (int) $row->stock_batch_id,
                    'product_id' => (int) $row->product_id,
                    'warehouse_id' => (int) $row->warehouse_id,
                    'unit_cost' => (float) $row->unit_cost,
                    'stock' => $stockQuantity,
                    'layers' => $layerQuantity,
                ];
            }
        }

        // A layer for a batch current_stock_by_batch has no row for at all.
        foreach ($layers as $key => $row) {
            if (isset($seen[$key]) || round((float) $row->quantity, 3) <= self::QTY_EPSILON) {
                continue;
            }

            $batch = DB::table('stock_batches')->where('id', $row->stock_batch_id)->first(['product_id', 'unit_cost']);

            if (! $batch || ! $productIds->contains($batch->product_id)) {
                continue;
            }

            $drift[] = [
                'batch_id' => (int) $row->stock_batch_id,
                'product_id' => (int) $batch->product_id,
                'warehouse_id' => (int) $row->warehouse_id,
                'unit_cost' => (float) $batch->unit_cost,
                'stock' => 0.0,
                'layers' => round((float) $row->quantity, 3),
            ];
        }

        return $drift;
    }

    /**
     * Per product and warehouse: the ledger balance, current_stock_by_batch, the valuation
     * layers and current_stock, all four of which should be the same number.
     *
     * @return array<string, array{product_id: int, warehouse_id: int, ledger: float, stock: float, layers: float, current: float}>
     */
    private function productsOutOfStep(Collection $productIds): array
    {
        $ledger = DB::table('stock_movements')
            ->whereIn('product_id', $productIds)
            ->whereNotNull('warehouse_id')
            ->whereIn('movement_type', self::WAREHOUSE_MOVEMENT_TYPES)
            ->selectRaw('product_id, warehouse_id, SUM(quantity) as quantity')
            ->groupBy('product_id', 'warehouse_id')
            ->get()
            ->keyBy(fn ($row) => $row->product_id.'_'.$row->warehouse_id);

        $stock = DB::table('current_stock_by_batch')
            ->whereIn('product_id', $productIds)
            ->selectRaw('product_id, warehouse_id, SUM(quantity_on_hand) as quantity')
            ->groupBy('product_id', 'warehouse_id')
            ->get()
            ->keyBy(fn ($row) => $row->product_id.'_'.$row->warehouse_id);

        $layers = DB::table('stock_valuation_layers')
            ->whereIn('product_id', $productIds)
            ->selectRaw('product_id, warehouse_id, SUM(quantity_remaining) as quantity')
            ->groupBy('product_id', 'warehouse_id')
            ->get()
            ->keyBy(fn ($row) => $row->product_id.'_'.$row->warehouse_id);

        $current = DB::table('current_stock')
            ->whereIn('product_id', $productIds)
            ->selectRaw('product_id, warehouse_id, SUM(quantity_on_hand) as quantity')
            ->groupBy('product_id', 'warehouse_id')
            ->get()
            ->keyBy(fn ($row) => $row->product_id.'_'.$row->warehouse_id);

        $keys = collect([$ledger, $stock, $layers, $current])
            ->flatMap(fn ($rows) => $rows->keys())
            ->unique();

        $drift = [];

        foreach ($keys as $key) {
            $quantities = [
                'ledger' => round((float) ($ledger[$key]->quantity ?? 0), 3),
                'stock' => round((float) ($stock[$key]->quantity ?? 0), 3),
                'layers' => round((float) ($layers[$key]->quantity ?? 0), 3),
                'current' => round((float) ($current[$key]->quantity ?? 0), 3),
            ];

            if (max($quantities) - min($quantities) <= self::QTY_EPSILON) {
                continue;
            }

            [$productId, $warehouseId] = explode('_', (string) $key);

            $drift[$key] = ['product_id' => (int) $productId, 'warehouse_id' => (int) $warehouseId] + $quantities;
        }

        return $drift;
    }

    /**
     * @param  array<string, array{product_id: int, warehouse_id: int, ledger: float, stock: float, layers: float, current: float}>  $drift
     */
    private function reportProductDrift(array $drift): void
    {
        if ($drift === []) {
            return;
        }

        $names = DB::table('products')
            ->whereIn('id', collect($drift)->pluck('product_id'))
            ->pluck('product_name', 'id');

        $this->warn('These products do not agree across the four stock records:');
        $this->table(
            ['Product', 'Name', 'Wh', 'Ledger', 'By batch', 'Layers', 'current_stock'],
            collect($drift)->map(fn (array $row) => [
                $row['product_id'],
                $names[$row['product_id']] ?? '—',
                $row['warehouse_id'],
                number_format($row['ledger'], 3),
                number_format($row['stock'], 3),
                number_format($row['layers'], 3),
                number_format($row['current'], 3),
            ])->values()->all()
        );
    }

    /**
     * @param  array<int, array{batch_id: int, product_id: int, warehouse_id: int, stock: float, layers: float}>  $drift
     */
    private function reportBatchDrift(array $drift): void
    {
        if ($drift === []) {
            return;
        }

        $this->warn('These batches hold a different quantity in their valuation layers than in current stock:');
        $this->table(
            ['Batch', 'Product', 'Wh', 'By batch', 'Layers'],
            collect($drift)->map(fn (array $row) => [
                $row['batch_id'],
                $row['product_id'],
                $row['warehouse_id'],
                number_format($row['stock'], 3),
                number_format($row['layers'], 3),
            ])->all()
        );
    }

    /**
     * Tell whoever receives the backup emails, so an unattended nightly run does not leave
     * the finding sitting in the log.
     *
     * @param  array<string, array{product_id: int, warehouse_id: int, ledger: float, stock: float, layers: float, current: float}>  $productDrift
     * @param  array<int, array{batch_id: int, product_id: int, warehouse_id: int, stock: float, layers: float}>  $batchDrift
     */
    private function emailMismatches(array $productDrift, array $batchDrift, ?string $supplierId): void
    {
        $recipient = config('backup.notifications.mail.to');

        if (! $recipient) {
            return;
        }

        $names = DB::table('products')
            ->whereIn('id', collect($productDrift)->pluck('product_id')->merge(collect($batchDrift)->pluck('product_id')))
            ->pluck('product_name', 'id');

        $products = collect($productDrift)
            ->map(fn (array $row) => $row + ['name' => $names[$row['product_id']] ?? "Product {$row['product_id']}"])
            ->values()
            ->all();

        try {
            Notification::route('mail', $recipient)
                ->notify(new InventoryConsistencyMismatch($products, count($batchDrift), $supplierId));
        } catch (\Throwable $e) {
            // The warning above is already logged; a mail outage must not hide the finding.
            Log::error('Could not email the inventory consistency alert: '.$e->getMessage());
            $this->warn('Could not send the alert email: '.$e->getMessage());
        }
    }
}
