<?php

namespace App\Console\Commands\Stock;

use App\Models\DailyInventorySnapshot;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RebuildDailyInventorySnapshots extends Command
{
    /**
     * Warehouse-affecting movement types. Deliberately excludes 'sale' and 'shortage':
     * those rows also carry a warehouse_id for reference, but they represent stock that
     * already left the warehouse via a 'transfer' (goods issue) — counting them again
     * here would double-deduct van-side activity from the warehouse balance.
     */
    private const WAREHOUSE_MOVEMENT_TYPES = ['grn', 'transfer', 'adjustment', 'return', 'damage', 'theft'];

    protected $signature = 'inventory:snapshots:rebuild
        {start_date : First date to rebuild (Y-m-d)}
        {end_date : Last date to rebuild (Y-m-d, inclusive)}
        {--supplier_id= : Only rebuild snapshots for products of this supplier}
        {--dry-run : Preview computed values without saving}';

    protected $description = 'Recompute daily_inventory_snapshots (warehouse rows) for a date range from the '
        .'stock_movements ledger (keyed by real movement_date), instead of the current-state snapshot job. '
        .'Use this to repair dates where snapshots were created before backdated documents (e.g. a GRN drafted '
        .'offline and posted days later) were posted, leaving stale/frozen values.';

    public function handle(): int
    {
        $startDate = $this->argument('start_date');
        $endDate = $this->argument('end_date');
        $supplierId = $this->option('supplier_id');
        $isDryRun = (bool) $this->option('dry-run');

        if ($startDate > $endDate) {
            $this->error('start_date must be before or equal to end_date.');

            return self::FAILURE;
        }

        $productQuery = DB::table('products')->whereNull('deleted_at');
        if ($supplierId) {
            $productQuery->where('supplier_id', $supplierId);
        }
        $productIds = $productQuery->pluck('id');

        if ($productIds->isEmpty()) {
            $this->warn('No matching products found — nothing to do.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Rebuilding warehouse snapshots for %d product(s)%s from %s to %s%s',
            $productIds->count(),
            $supplierId ? " (supplier_id={$supplierId})" : '',
            $startDate,
            $endDate,
            $isDryRun ? ' [DRY RUN]' : ''
        ));

        $period = CarbonPeriod::create($startDate, $endDate);
        $totalUpdated = 0;
        $totalDeleted = 0;

        foreach ($period as $date) {
            $dateString = $date->toDateString();

            $rows = DB::table('stock_movements as sm')
                ->whereIn('sm.product_id', $productIds)
                ->whereNotNull('sm.warehouse_id')
                ->whereIn('sm.movement_type', self::WAREHOUSE_MOVEMENT_TYPES)
                ->where('sm.movement_date', '<=', $dateString)
                ->select(
                    'sm.product_id',
                    'sm.warehouse_id',
                    DB::raw('SUM(sm.quantity) as quantity_on_hand'),
                    DB::raw('SUM(sm.quantity * sm.unit_cost) as total_value')
                )
                ->groupBy('sm.product_id', 'sm.warehouse_id')
                ->get();

            $updatedForDate = 0;

            foreach ($rows as $row) {
                $qty = round((float) $row->quantity_on_hand, 3);
                $value = round((float) $row->total_value, 2);

                if ($qty <= 0.001) {
                    // No stock as of this date for this product+warehouse — remove any
                    // stale (e.g. frozen) snapshot row that shouldn't exist.
                    $deleted = DailyInventorySnapshot::where('date', $dateString)
                        ->where('product_id', $row->product_id)
                        ->where('warehouse_id', $row->warehouse_id)
                        ->whereNull('vehicle_id')
                        ->delete();
                    $totalDeleted += $deleted;

                    continue;
                }

                $averageCost = round($value / $qty, 6);

                if ($isDryRun) {
                    $this->line(sprintf(
                        '  [%s] product_id=%d warehouse_id=%d qty=%.3f value=%.2f',
                        $dateString, $row->product_id, $row->warehouse_id, $qty, $value
                    ));
                } else {
                    DailyInventorySnapshot::updateOrCreate(
                        [
                            'date' => $dateString,
                            'product_id' => $row->product_id,
                            'warehouse_id' => $row->warehouse_id,
                            'vehicle_id' => null,
                        ],
                        [
                            'quantity_on_hand' => $qty,
                            'average_cost' => $averageCost,
                            'total_value' => $value,
                        ]
                    );
                }

                $updatedForDate++;
            }

            $totalUpdated += $updatedForDate;
            $this->line("{$dateString}: {$updatedForDate} snapshot row(s) rebuilt");
        }

        if ($isDryRun) {
            $this->warn('DRY RUN — no data was changed. Re-run without --dry-run to apply.');
        } else {
            $this->info("Done. {$totalUpdated} snapshot row(s) rebuilt, {$totalDeleted} stale row(s) removed.");
            Log::info('Rebuilt daily_inventory_snapshots from stock_movements ledger', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'supplier_id' => $supplierId,
                'updated' => $totalUpdated,
                'deleted' => $totalDeleted,
            ]);
        }

        return self::SUCCESS;
    }
}
