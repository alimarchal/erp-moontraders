<?php

namespace App\Console\Commands\Stock;

use App\Models\StockMovement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillRevaluationValues extends Command
{
    /** Movement types that change the quantity held in the warehouse. */
    private const WAREHOUSE_MOVEMENT_TYPES = ['grn', 'transfer', 'adjustment', 'return', 'damage', 'theft'];

    protected $signature = 'stock:backfill-revaluation-values
        {--dry-run : Report what would change without saving}';

    protected $description = 'Stock cost revaluations posted before the inventory ledger carried their value left that '
        .'row at zero, so the Daily Stock Register valued the stock differently from the daily snapshots. This writes '
        .'the value each revaluation moved its batch by onto its ledger row.';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $rows = [];

        $markers = StockMovement::query()->revaluations()->whereNotNull('stock_batch_id')->orderBy('id')->get();

        foreach ($markers as $marker) {
            $ledgerRow = DB::table('inventory_ledger_entries')
                ->where('stock_adjustment_id', $marker->reference_id)
                ->where('stock_batch_id', $marker->stock_batch_id)
                ->where('transaction_type', 'adjustment')
                ->where('debit_qty', 0)
                ->where('credit_qty', 0)
                ->where('total_value', 0)
                ->orderBy('id')
                ->first();

            if (! $ledgerRow) {
                continue;
            }

            $quantityBefore = (float) DB::table('stock_movements')
                ->where('stock_batch_id', $marker->stock_batch_id)
                ->where('id', '<', $marker->id)
                ->whereIn('movement_type', self::WAREHOUSE_MOVEMENT_TYPES)
                ->sum('quantity');

            // The cost the batch was carried at just before: that of the movement before it,
            // which for a second revaluation is the first one's zero-quantity row.
            $previousCost = DB::table('stock_movements')
                ->where('stock_batch_id', $marker->stock_batch_id)
                ->where('id', '<', $marker->id)
                ->orderByDesc('id')
                ->value('unit_cost');

            if ($previousCost === null) {
                continue;
            }

            $value = round($quantityBefore * ((float) $marker->unit_cost - round((float) $previousCost, 2)), 2);

            $rows[] = [$ledgerRow->id, $marker->product_id, $marker->stock_batch_id, number_format($quantityBefore, 2), number_format((float) $previousCost, 2), number_format((float) $marker->unit_cost, 2), number_format($value, 2)];

            if (! $isDryRun && abs($value) >= 0.01) {
                DB::table('inventory_ledger_entries')->where('id', $ledgerRow->id)->update(['total_value' => $value]);
            }
        }

        if ($rows === []) {
            $this->info('No revaluation ledger row needs a value.');

            return self::SUCCESS;
        }

        $this->table(['Ledger row', 'Product', 'Batch', 'Qty before', 'Old cost', 'New cost', 'Value'], $rows);
        $this->{$isDryRun ? 'warn' : 'info'}($isDryRun ? 'DRY RUN — no data was changed.' : 'Revaluation values written.');

        return self::SUCCESS;
    }
}
