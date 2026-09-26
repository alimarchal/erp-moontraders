<?php

namespace App\Console\Commands\Stock;

use App\Models\DailyInventorySnapshot;
use App\Notifications\SnapshotRebuildSkippedProducts;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class RebuildDailyInventorySnapshots extends Command
{
    /**
     * Warehouse-affecting movement types. Deliberately excludes 'sale' and 'shortage':
     * those rows also carry a warehouse_id for reference, but they represent stock that
     * already left the warehouse via a 'transfer' (goods issue) — counting them again
     * here would double-deduct van-side activity from the warehouse balance.
     */
    private const WAREHOUSE_MOVEMENT_TYPES = ['grn', 'transfer', 'adjustment', 'return', 'damage', 'theft'];

    /**
     * Van-affecting movement types, with the sign that converts the stored quantity into a
     * van delta. A 'transfer' is stored negative because it leaves the warehouse, but it is
     * what puts stock on the van; a 'return' is stored positive because it arrives back at
     * the warehouse, and it is what takes stock off the van.
     */
    private const VAN_MOVEMENT_SIGNS = ['transfer' => -1, 'sale' => 1, 'return' => -1, 'shortage' => 1];

    /** Rows written per upsert statement. */
    private const UPSERT_CHUNK = 500;

    /** Quantities below this are treated as zero stock. */
    private const QTY_EPSILON = 0.001;

    protected $signature = 'inventory:snapshots:rebuild
        {start_date? : First date to rebuild (Y-m-d). Omit to rebuild the --days window ending at end_date}
        {end_date? : Last date to rebuild (Y-m-d, inclusive). Defaults to today}
        {--days=90 : Window length used when start_date is omitted}
        {--supplier_id= : Only rebuild snapshots for products of this supplier}
        {--with-vans : Also rebuild the vehicle rows, which no other job writes}
        {--include-backdated=0 : Also reach back to the oldest movement_date posted in the last N days, when that falls before the --days window}
        {--dry-run : Report what would change without saving}
        {--force : Also rebuild products whose ledger disagrees with current stock}';

    protected $description = 'Recompute daily_inventory_snapshots for a date range from the stock_movements '
        .'ledger (keyed by real movement_date), instead of the current-state snapshot job. Use this to repair '
        .'dates whose snapshots were taken before backdated documents (e.g. a goods issue settled days later) '
        .'were posted, leaving stale/frozen values.';

    /**
     * Stock is valued per batch at the batch's receipt (GRN) cost rather than by summing each
     * movement's own signed value. Issue movements occasionally record a unit_cost that
     * differs from what the batch was received at; summing those leaves a residue in the
     * warehouse value that corresponds to no physical stock. Valuing the surviving quantity
     * at its receipt cost is what current_stock_by_batch does, so a rebuilt snapshot for
     * today reconciles exactly with live stock.
     *
     * @var array<int, float>
     */
    private array $batchCosts = [];

    public function handle(): int
    {
        $endDate = $this->parseDate($this->argument('end_date') ?? now()->toDateString(), 'end_date');

        if ($endDate === null) {
            return self::FAILURE;
        }

        // The ledger knows nothing about days that have not happened yet; rows written for them
        // would only go stale once those days' documents are posted.
        $today = now()->toDateString();
        if ($endDate > $today) {
            $this->warn("end_date {$endDate} is in the future — rebuilding up to today ({$today}) only.");
            $endDate = $today;
        }

        $startDate = $this->argument('start_date') !== null
            ? $this->parseDate($this->argument('start_date'), 'start_date')
            : Carbon::parse($endDate)->subDays(max(0, (int) $this->option('days')))->toDateString();

        if ($startDate === null) {
            return self::FAILURE;
        }

        if ($this->argument('start_date') === null) {
            $startDate = $this->extendForBackdatedPostings($startDate);
        }

        if ($startDate > $endDate) {
            $this->error('start_date must be before or equal to end_date.');

            return self::FAILURE;
        }

        $supplierId = $this->option('supplier_id');
        $isDryRun = (bool) $this->option('dry-run');

        $productQuery = DB::table('products')->whereNull('deleted_at');
        if ($supplierId) {
            $productQuery->where('supplier_id', $supplierId);
        }
        $productIds = $productQuery->pluck('id');

        if ($productIds->isEmpty()) {
            $this->warn('No matching products found — nothing to do.');

            return self::SUCCESS;
        }

        $outOfStep = $this->productsOutOfStepWithCurrentStock($productIds);

        if ($outOfStep !== []) {
            $this->reportProductsOutOfStep($outOfStep);

            if (! $this->option('force')) {
                $productIds = $productIds->reject(fn ($productId) => isset($outOfStep[$productId]))->values();

                if (! $isDryRun) {
                    Log::warning('Snapshot rebuild skipped products whose ledger disagrees with current stock', [
                        'products' => $outOfStep,
                    ]);
                    $this->emailSkippedProducts($outOfStep, $startDate, $endDate, $supplierId);
                }

                if ($productIds->isEmpty()) {
                    $this->error('Nothing left to rebuild.');

                    return self::FAILURE;
                }
            }
        }

        $this->info(sprintf(
            'Rebuilding snapshots for %d product(s)%s from %s to %s%s',
            $productIds->count(),
            $supplierId ? " (supplier_id={$supplierId})" : '',
            $startDate,
            $endDate,
            $isDryRun ? ' [DRY RUN]' : ''
        ));

        $this->batchCosts = $this->loadBatchCosts();
        $this->warnAboutMovementsPricedOffBatchCost($productIds, $endDate);

        $passes = ['warehouse_id' => array_fill_keys(self::WAREHOUSE_MOVEMENT_TYPES, 1)];
        if ($this->option('with-vans')) {
            $passes['vehicle_id'] = self::VAN_MOVEMENT_SIGNS;
        }

        $summary = [];

        foreach ($passes as $locationColumn => $signs) {
            $this->newLine();
            $this->line($locationColumn === 'vehicle_id' ? '<comment>Vehicles</comment>' : '<comment>Warehouses</comment>');

            $summary[$locationColumn] = $this->rebuildPass(
                $locationColumn, $signs, $productIds, $startDate, $endDate, $isDryRun
            );
        }

        $this->newLine();
        $this->table(
            ['Location', 'Created', 'Changed', 'Unchanged', 'Stale removed', "Qty @ {$endDate}", "Value @ {$endDate}"],
            collect($summary)->map(fn (array $totals, string $column) => [
                $column === 'vehicle_id' ? 'Vehicles' : 'Warehouses',
                $totals['created'], $totals['updated'], $totals['unchanged'], $totals['deleted'],
                number_format($totals['closing_quantity'], 3), number_format($totals['closing_value'], 2),
            ])->values()->all()
        );

        $skippedProducts = $outOfStep !== [] && ! $this->option('force');

        if ($isDryRun) {
            $this->warn('DRY RUN — no data was changed. Re-run without --dry-run to apply.');

            return $skippedProducts ? self::FAILURE : self::SUCCESS;
        }

        $this->info('Done.');
        Log::info('Rebuilt daily_inventory_snapshots from stock_movements ledger', [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'supplier_id' => $supplierId,
            'locations' => array_keys($passes),
            'totals' => $summary,
        ]);

        if ($skippedProducts) {
            $this->warn(count($outOfStep).' product(s) above were skipped. Their snapshots were left as they were.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * A document posted today can be dated months back (work caught up after a holiday or
     * lockdown). A fixed window would leave every snapshot before it frozen at the value it
     * held before that document existed, so the window is stretched back to the oldest date
     * anything was recently posted against.
     */
    private function extendForBackdatedPostings(string $startDate): string
    {
        $lookbackDays = max(0, (int) $this->option('include-backdated'));

        if ($lookbackDays === 0) {
            return $startDate;
        }

        $oldestBackdated = DB::table('stock_movements')
            ->where('created_at', '>=', now()->subDays($lookbackDays)->startOfDay())
            ->min('movement_date');

        if ($oldestBackdated === null) {
            return $startDate;
        }

        $oldestBackdated = Carbon::parse($oldestBackdated)->toDateString();

        if ($oldestBackdated >= $startDate) {
            return $startDate;
        }

        $this->warn("Documents posted in the last {$lookbackDays} day(s) are dated as far back as {$oldestBackdated} — rebuilding from there instead of {$startDate}.");

        return $oldestBackdated;
    }

    /**
     * Snapshots are replayed from the movement ledger, so they can only be right for a product
     * whose ledger ends where current stock stands. A product that does not is left alone
     * rather than overwritten with figures that match neither.
     *
     * @return array<int, array{ledger: float, stock: float}>
     */
    private function productsOutOfStepWithCurrentStock(Collection $productIds): array
    {
        $ledger = DB::table('stock_movements')
            ->whereIn('product_id', $productIds)
            ->whereNotNull('warehouse_id')
            ->whereIn('movement_type', self::WAREHOUSE_MOVEMENT_TYPES)
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) as quantity')
            ->pluck('quantity', 'product_id');

        $stock = DB::table('current_stock_by_batch')
            ->whereIn('product_id', $productIds)
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity_on_hand) as quantity')
            ->pluck('quantity', 'product_id');

        $outOfStep = [];

        foreach ($ledger->keys()->merge($stock->keys())->unique() as $productId) {
            $ledgerQuantity = round((float) ($ledger[$productId] ?? 0), 3);
            $stockQuantity = round((float) ($stock[$productId] ?? 0), 3);

            if (abs($ledgerQuantity - $stockQuantity) > self::QTY_EPSILON) {
                $outOfStep[(int) $productId] = ['ledger' => $ledgerQuantity, 'stock' => $stockQuantity];
            }
        }

        return $outOfStep;
    }

    /**
     * Tell whoever receives the backup emails, so a product skipped by the unattended
     * nightly run does not go unnoticed in the log.
     *
     * @param  array<int, array{ledger: float, stock: float}>  $outOfStep
     */
    private function emailSkippedProducts(array $outOfStep, string $startDate, string $endDate, ?string $supplierId): void
    {
        $recipient = config('backup.notifications.mail.to');

        if (! $recipient) {
            return;
        }

        $names = DB::table('products')->whereIn('id', array_keys($outOfStep))->pluck('product_name', 'id');
        $products = collect($outOfStep)
            ->map(fn (array $quantities, int $productId) => ['name' => $names[$productId] ?? "Product {$productId}"] + $quantities)
            ->all();

        try {
            Notification::route('mail', $recipient)
                ->notify(new SnapshotRebuildSkippedProducts($products, $startDate, $endDate, $supplierId));
        } catch (\Throwable $e) {
            // The warning above is already logged; a mail outage must not stop the rebuild.
            Log::error('Could not email the snapshot rebuild skip alert: '.$e->getMessage());
            $this->warn('Could not send the alert email: '.$e->getMessage());
        }
    }

    /**
     * @param  array<int, array{ledger: float, stock: float}>  $outOfStep
     */
    private function reportProductsOutOfStep(array $outOfStep): void
    {
        $names = DB::table('products')->whereIn('id', array_keys($outOfStep))->pluck('product_name', 'id');

        $this->warn('The stock ledger does not match current stock for these products:');
        $this->table(
            ['Product', 'Name', 'Ledger qty', 'Current stock qty'],
            collect($outOfStep)->map(fn (array $quantities, int $productId) => [
                $productId,
                $names[$productId] ?? '—',
                number_format($quantities['ledger'], 3),
                number_format($quantities['stock'], 3),
            ])->values()->all()
        );
        $this->warn($this->option('force')
            ? '--force given: rebuilding them from the ledger anyway.'
            : 'They are skipped. Correct the ledger first, or re-run with --force to rebuild them from the ledger anyway.');
    }

    /**
     * Replay one location dimension (warehouses or vehicles) across the window.
     *
     * @param  array<string, int>  $signs
     * @return array{created: int, updated: int, unchanged: int, deleted: int, closing_quantity: float, closing_value: float}
     */
    private function rebuildPass(
        string $locationColumn,
        array $signs,
        Collection $productIds,
        string $startDate,
        string $endDate,
        bool $isDryRun
    ): array {
        $deltas = $this->loadDailyBatchDeltas($productIds, $endDate, $locationColumn, $signs);

        // Carry every movement dated before the window into the opening balance, so the
        // first rebuilt date starts from the true position rather than from zero.
        $balances = [];
        foreach ($deltas as $movementDate => $dayDeltas) {
            if ($movementDate >= $startDate) {
                continue;
            }

            $this->applyDeltas($balances, $dayDeltas);
            unset($deltas[$movementDate]);
        }

        $totals = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'deleted' => 0];
        $closingQuantity = 0.0;
        $closingValue = 0.0;

        foreach (CarbonPeriod::create($startDate, $endDate) as $date) {
            $dateString = $date->toDateString();

            if (isset($deltas[$dateString])) {
                $this->applyDeltas($balances, $deltas[$dateString]);
                unset($deltas[$dateString]);
            }

            $rows = $this->buildSnapshotRows($balances, $dateString, $locationColumn);
            $result = $isDryRun
                ? $this->previewDate($dateString, $rows, $productIds, $locationColumn)
                : $this->persistDate($dateString, $rows, $productIds, $locationColumn);

            foreach ($totals as $key => $value) {
                $totals[$key] = $value + $result[$key];
            }

            $closingQuantity = array_sum(array_column($rows, 'quantity_on_hand'));
            $closingValue = array_sum(array_column($rows, 'total_value'));

            $this->line(sprintf(
                '%s: %d row(s) — %d new, %d changed, %d unchanged, %d stale removed',
                $dateString, count($rows), $result['created'], $result['updated'],
                $result['unchanged'], $result['deleted']
            ));
        }

        return $totals + ['closing_quantity' => $closingQuantity, 'closing_value' => $closingValue];
    }

    private function parseDate(string $value, string $label): ?string
    {
        $date = Carbon::hasFormat($value, 'Y-m-d') ? Carbon::createFromFormat('Y-m-d', $value) : null;

        if ($date === null) {
            $this->error("{$label} must be a valid date in Y-m-d format, got \"{$value}\".");

            return null;
        }

        return $date->toDateString();
    }

    /**
     * Receipt cost per batch, taken from the batch's own GRN movement(s) and falling back
     * to the batch master when a batch has no GRN row (e.g. opening-stock batches).
     *
     * @return array<int, float>
     */
    private function loadBatchCosts(): array
    {
        $costs = DB::table('stock_movements')
            ->where('movement_type', 'grn')
            ->whereNotNull('stock_batch_id')
            ->groupBy('stock_batch_id')
            ->havingRaw('SUM(quantity) <> 0')
            ->select('stock_batch_id', DB::raw('SUM(quantity * unit_cost) / SUM(quantity) as receipt_cost'))
            ->pluck('receipt_cost', 'stock_batch_id')
            ->map(fn ($cost) => (float) $cost)
            ->all();

        $fallback = DB::table('stock_batches')
            ->whereNotIn('id', array_keys($costs) ?: [0])
            ->pluck('unit_cost', 'id')
            ->map(fn ($cost) => (float) $cost)
            ->all();

        return $costs + $fallback;
    }

    /**
     * Net movement per (date, product, location, batch), oldest first.
     *
     * @param  array<string, int>  $signs
     * @return array<string, list<array{product_id: int, location_id: int, stock_batch_id: int|null, quantity: float}>>
     */
    private function loadDailyBatchDeltas(
        Collection $productIds,
        string $endDate,
        string $locationColumn,
        array $signs
    ): array {
        $deltas = [];

        DB::table('stock_movements')
            ->whereIn('product_id', $productIds)
            ->whereNotNull($locationColumn)
            ->whereIn('movement_type', array_keys($signs))
            ->where('movement_date', '<=', $endDate)
            ->groupBy('movement_date', 'product_id', $locationColumn, 'stock_batch_id', 'movement_type')
            ->orderBy('movement_date')
            ->select(
                'movement_date',
                'product_id',
                $locationColumn.' as location_id',
                'stock_batch_id',
                'movement_type',
                DB::raw('SUM(quantity) as quantity')
            )
            ->each(function ($row) use (&$deltas, $signs): void {
                $deltas[Carbon::parse($row->movement_date)->toDateString()][] = [
                    'product_id' => (int) $row->product_id,
                    'location_id' => (int) $row->location_id,
                    'stock_batch_id' => $row->stock_batch_id === null ? null : (int) $row->stock_batch_id,
                    'quantity' => $signs[$row->movement_type] * (float) $row->quantity,
                ];
            });

        ksort($deltas);

        return $deltas;
    }

    /**
     * @param  array<int, array<int, array<int|string, float>>>  $balances
     * @param  list<array{product_id: int, location_id: int, stock_batch_id: int|null, quantity: float}>  $dayDeltas
     */
    private function applyDeltas(array &$balances, array $dayDeltas): void
    {
        foreach ($dayDeltas as $delta) {
            $batchKey = $delta['stock_batch_id'] ?? 'unbatched';
            $current = $balances[$delta['product_id']][$delta['location_id']][$batchKey] ?? 0.0;
            $balances[$delta['product_id']][$delta['location_id']][$batchKey] = $current + $delta['quantity'];
        }
    }

    /**
     * Collapse per-batch balances into the snapshot rows that should exist for a date.
     *
     * @param  array<int, array<int, array<int|string, float>>>  $balances
     * @return list<array<string, mixed>>
     */
    private function buildSnapshotRows(array $balances, string $dateString, string $locationColumn): array
    {
        $rows = [];

        foreach ($balances as $productId => $locations) {
            foreach ($locations as $locationId => $batches) {
                $quantity = 0.0;
                $value = 0.0;

                foreach ($batches as $batchKey => $batchQuantity) {
                    if (abs($batchQuantity) < self::QTY_EPSILON) {
                        continue;
                    }

                    $quantity += $batchQuantity;
                    $value += $batchQuantity * ($this->batchCosts[$batchKey] ?? 0.0);
                }

                $quantity = round($quantity, 3);

                if ($quantity <= self::QTY_EPSILON) {
                    continue;
                }

                $value = round($value, 2);

                $rows[] = [
                    'date' => $dateString,
                    'product_id' => $productId,
                    'warehouse_id' => $locationColumn === 'warehouse_id' ? $locationId : null,
                    'vehicle_id' => $locationColumn === 'vehicle_id' ? $locationId : null,
                    'quantity_on_hand' => $quantity,
                    'average_cost' => round($value / $quantity, 6),
                    'total_value' => $value,
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{created: int, updated: int, unchanged: int, deleted: int}
     */
    private function persistDate(string $dateString, array $rows, Collection $productIds, string $locationColumn): array
    {
        return DB::transaction(function () use ($dateString, $rows, $productIds, $locationColumn): array {
            $existing = $this->existingSnapshots($dateString, $productIds, $locationColumn);
            $counts = $this->classify($rows, $existing, $locationColumn);

            foreach (array_chunk($rows, self::UPSERT_CHUNK) as $chunk) {
                DailyInventorySnapshot::upsert(
                    $chunk,
                    ['date', 'product_id', $locationColumn],
                    ['quantity_on_hand', 'average_cost', 'total_value']
                );
            }

            $staleIds = $this->staleSnapshotIds($rows, $existing, $locationColumn);

            if ($staleIds !== []) {
                DailyInventorySnapshot::whereIn('id', $staleIds)->delete();
            }

            $counts['deleted'] = count($staleIds);

            return $counts;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{created: int, updated: int, unchanged: int, deleted: int}
     */
    private function previewDate(string $dateString, array $rows, Collection $productIds, string $locationColumn): array
    {
        $existing = $this->existingSnapshots($dateString, $productIds, $locationColumn);
        $counts = $this->classify($rows, $existing, $locationColumn);
        $counts['deleted'] = count($this->staleSnapshotIds($rows, $existing, $locationColumn));

        if ($this->output->isVerbose()) {
            foreach ($rows as $row) {
                $before = $existing[$this->rowKey($row, $locationColumn)] ?? null;

                if ($before !== null && $this->matches($before, $row)) {
                    continue;
                }

                $this->line(sprintf(
                    '  [%s] product_id=%d %s=%d qty %s -> %.3f, value %s -> %.2f',
                    $dateString, $row['product_id'], $locationColumn, $row[$locationColumn],
                    $before === null ? '(none)' : number_format((float) $before->quantity_on_hand, 3),
                    $row['quantity_on_hand'],
                    $before === null ? '(none)' : number_format((float) $before->total_value, 2),
                    $row['total_value']
                ));
            }
        }

        return $counts;
    }

    /**
     * Snapshot rows already stored for a date, keyed by "product_id|location_id".
     *
     * @return array<string, object>
     */
    private function existingSnapshots(string $dateString, Collection $productIds, string $locationColumn): array
    {
        $otherColumn = $locationColumn === 'warehouse_id' ? 'vehicle_id' : 'warehouse_id';

        return DB::table('daily_inventory_snapshots')
            ->where('date', $dateString)
            ->whereNotNull($locationColumn)
            ->whereNull($otherColumn)
            ->whereIn('product_id', $productIds)
            ->get(['id', 'product_id', $locationColumn, 'quantity_on_hand', 'total_value'])
            ->keyBy(fn ($row) => $row->product_id.'|'.$row->{$locationColumn})
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowKey(array $row, string $locationColumn): string
    {
        return $row['product_id'].'|'.$row[$locationColumn];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function matches(object $stored, array $row): bool
    {
        return abs((float) $stored->quantity_on_hand - $row['quantity_on_hand']) < self::QTY_EPSILON
            && abs((float) $stored->total_value - $row['total_value']) < 0.01;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, object>  $existing
     * @return array{created: int, updated: int, unchanged: int, deleted: int}
     */
    private function classify(array $rows, array $existing, string $locationColumn): array
    {
        $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'deleted' => 0];

        foreach ($rows as $row) {
            $before = $existing[$this->rowKey($row, $locationColumn)] ?? null;

            if ($before === null) {
                $counts['created']++;
            } elseif ($this->matches($before, $row)) {
                $counts['unchanged']++;
            } else {
                $counts['updated']++;
            }
        }

        return $counts;
    }

    /**
     * Snapshot rows the ledger no longer supports for this date — for example a row frozen
     * by the nightly job before the document that emptied the location was backdated in.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, object>  $existing
     * @return list<int>
     */
    private function staleSnapshotIds(array $rows, array $existing, string $locationColumn): array
    {
        $keep = [];
        foreach ($rows as $row) {
            $keep[$this->rowKey($row, $locationColumn)] = true;
        }

        $stale = [];
        foreach ($existing as $key => $row) {
            if (! isset($keep[$key])) {
                $stale[] = (int) $row->id;
            }
        }

        return $stale;
    }

    /**
     * Outbound movements whose unit_cost differs from the batch's receipt cost are a ledger
     * defect, not a rebuild problem — but they are worth surfacing, because they are the
     * reason a value summed from movements drifts away from live stock.
     */
    private function warnAboutMovementsPricedOffBatchCost(Collection $productIds, string $endDate): void
    {
        $suspects = DB::table('stock_movements')
            ->whereIn('product_id', $productIds)
            ->whereNotNull('warehouse_id')
            ->whereNotNull('stock_batch_id')
            ->whereIn('movement_type', self::WAREHOUSE_MOVEMENT_TYPES)
            ->where('movement_type', '!=', 'grn')
            ->where('movement_date', '<=', $endDate)
            ->get(['id', 'movement_date', 'reference_type', 'reference_id', 'product_id', 'stock_batch_id', 'quantity', 'unit_cost'])
            ->filter(function ($movement): bool {
                $batchCost = $this->batchCosts[(int) $movement->stock_batch_id] ?? null;

                return $batchCost !== null && abs((float) $movement->unit_cost - $batchCost) > 0.01;
            });

        if ($suspects->isEmpty()) {
            return;
        }

        $this->warn(sprintf(
            '%d movement(s) are priced off their batch cost (ledger defect). Snapshots use the batch '
            .'receipt cost, so they are unaffected — but COGS on those documents is wrong. Run with -v to list them.',
            $suspects->count()
        ));

        if ($this->output->isVerbose()) {
            $this->table(
                ['Movement', 'Date', 'Document', 'Product', 'Batch', 'Qty', 'Movement cost', 'Batch cost'],
                $suspects->map(fn ($movement) => [
                    $movement->id,
                    Carbon::parse($movement->movement_date)->toDateString(),
                    class_basename($movement->reference_type ?? '—').' #'.$movement->reference_id,
                    $movement->product_id,
                    $movement->stock_batch_id,
                    $movement->quantity,
                    $movement->unit_cost,
                    number_format($this->batchCosts[(int) $movement->stock_batch_id], 6),
                ])->all()
            );
        }
    }
}
