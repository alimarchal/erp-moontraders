<?php

namespace App\Console\Commands\Stock;

use App\Models\StockBatch;
use App\Models\StockMovement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CorrectDepletedBatchCost extends Command
{
    private const QTY_EPSILON = 0.0005;

    protected $signature = 'stock:correct-depleted-batch-cost
        {batch : Batch id or batch code}
        {cost : The unit cost the batch should carry}
        {--dry-run : Report what would change without saving}';

    protected $description = 'Put a wrong unit cost on an empty batch right (batch master, valuation layers and current stock rows). '
        .'Only a batch with nothing in any warehouse or on any van can be corrected, so no stock value or journal changes; '
        .'a batch that still holds stock must be revalued through a stock adjustment instead.';

    public function handle(): int
    {
        $batch = StockBatch::query()
            ->where('batch_code', $this->argument('batch'))
            ->when(ctype_digit((string) $this->argument('batch')), fn ($query) => $query->orWhere('id', (int) $this->argument('batch')))
            ->first();

        if (! $batch) {
            $this->error("No batch found for '{$this->argument('batch')}'.");

            return self::FAILURE;
        }

        $newCost = round((float) $this->argument('cost'), 6);

        if ($newCost <= 0) {
            $this->error('The cost must be greater than zero.');

            return self::FAILURE;
        }

        $inWarehouses = (float) DB::table('current_stock_by_batch')->where('stock_batch_id', $batch->id)->sum('quantity_on_hand');
        $inLayers = (float) DB::table('stock_valuation_layers')->where('stock_batch_id', $batch->id)->sum('quantity_remaining');
        $onVans = $this->quantityOnVans($batch->id);

        if ($inWarehouses > self::QTY_EPSILON || $inLayers > self::QTY_EPSILON || $onVans > self::QTY_EPSILON) {
            $this->error(sprintf(
                'Batch %s still holds stock (warehouses %s, valuation layers %s, vans %s). Changing its cost would change the stock value, so post a stock adjustment instead.',
                $batch->batch_code, $inWarehouses, $inLayers, $onVans
            ));

            return self::FAILURE;
        }

        $layers = DB::table('stock_valuation_layers')->where('stock_batch_id', $batch->id)->get(['id', 'unit_cost', 'quantity_received', 'total_value']);

        $this->table(['Batch', 'Product', 'Current cost', 'New cost', 'Receipt cost (layer value ÷ qty received)'], [[
            $batch->batch_code,
            $batch->product_id,
            number_format((float) $batch->unit_cost, 2, '.', ''),
            number_format($newCost, 2, '.', ''),
            $layers->map(fn ($layer) => (float) $layer->quantity_received > 0 ? number_format((float) $layer->total_value / (float) $layer->quantity_received, 2, '.', '') : '-')->implode(', '),
        ]]);

        if (abs((float) $batch->unit_cost - $newCost) < 0.000001
            && ! DB::table('stock_valuation_layers')->where('stock_batch_id', $batch->id)->where('unit_cost', '!=', $newCost)->exists()
            && ! DB::table('current_stock_by_batch')->where('stock_batch_id', $batch->id)->where('unit_cost', '!=', $newCost)->exists()) {
            $this->info("Batch {$batch->batch_code} already carries {$newCost}. Nothing to change.");

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN — no data was changed.');

            return self::SUCCESS;
        }

        $oldCost = (float) $batch->unit_cost;

        DB::transaction(function () use ($batch, $newCost) {
            StockBatch::whereKey($batch->id)->update(['unit_cost' => $newCost]);
            DB::table('stock_valuation_layers')->where('stock_batch_id', $batch->id)->update(['unit_cost' => $newCost, 'value_remaining' => 0]);
            DB::table('current_stock_by_batch')->where('stock_batch_id', $batch->id)->update(['unit_cost' => $newCost, 'total_value' => 0]);
        });

        Log::info('Depleted batch cost corrected', ['stock_batch_id' => $batch->id, 'batch_code' => $batch->batch_code, 'old_cost' => $oldCost, 'new_cost' => $newCost]);
        activity()->performedOn($batch)->withProperties(['old_cost' => $oldCost, 'new_cost' => $newCost])->log("Unit cost of empty batch {$batch->batch_code} corrected from {$oldCost} to {$newCost}");

        $this->info("Batch {$batch->batch_code} now carries {$newCost}.");

        return self::SUCCESS;
    }

    /**
     * What of a batch is on vans: issued, less what was sold, returned or found short.
     */
    private function quantityOnVans(int $stockBatchId): float
    {
        return (float) StockMovement::where('stock_batch_id', $stockBatchId)
            ->whereNotNull('vehicle_id')
            ->selectRaw("COALESCE(SUM(CASE movement_type
                WHEN 'transfer' THEN -quantity
                WHEN 'sale' THEN quantity
                WHEN 'return' THEN -quantity
                WHEN 'shortage' THEN quantity
                ELSE 0 END), 0) as quantity")
            ->value('quantity');
    }
}
