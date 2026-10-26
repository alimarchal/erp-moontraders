<?php

namespace App\Console\Commands\Stock;

use App\Services\GrnCostDriftService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RepairGrnCostDrift extends Command
{
    protected $signature = 'stock:repair-grn-cost-drift
        {--grn_id= : Only repair this GRN}
        {--dry-run : Report what would change without saving}';

    protected $description = 'Bring every GRN line whose receipt movement, batch, valuation layer or later issues/sales '
        .'carry a cost other than the line\'s unit_cost back onto that cost. Re-costs what was issued, sold and returned, '
        .'restates settlement COGS and posts the difference to the general ledger. Revalued batches are left alone.';

    public function handle(GrnCostDriftService $service): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $drifted = $service->detect($this->option('grn_id') ? (int) $this->option('grn_id') : null);

        if ($drifted->isEmpty()) {
            $this->info('Every GRN line agrees with its receipt movement, batch and issues — nothing to do.');

            return self::SUCCESS;
        }

        $this->warn(sprintf('%d GRN line(s) carry a cost that differs from their unit_cost.', $drifted->count()));

        $rows = [];
        $failed = 0;
        $cogsDelta = 0.0;

        foreach ($drifted as $drift) {
            try {
                $result = $service->repair($drift, $isDryRun);
            } catch (\Throwable $e) {
                $failed++;
                $rows[] = [$drift->grn_number, $drift->product_id, $drift->stock_batch_id, implode(',', $drift->problems), '—', '—', '—', 'FAILED: '.$e->getMessage()];

                continue;
            }

            $cogsDelta += $result['cogs_delta'];
            $rows[] = [
                $drift->grn_number,
                $drift->product_id,
                $drift->stock_batch_id,
                implode(',', $drift->problems),
                number_format($drift->expected_cost, 6),
                $result['movements'],
                number_format($result['cogs_delta'], 2),
                $result['notes'] === [] ? 'ok' : implode(' ', $result['notes']),
            ];
        }

        $this->table(['GRN', 'Product', 'Batch', 'Problems', 'Unit cost', 'Movements', 'COGS delta', 'Result'], $rows);

        if ($isDryRun) {
            $this->warn('DRY RUN — no data was changed. Re-run without --dry-run to apply.');

            return $failed > 0 ? self::FAILURE : self::SUCCESS;
        }

        Log::info('Repaired GRN cost drift', ['lines' => $drifted->count() - $failed, 'failed' => $failed, 'cogs_delta' => round($cogsDelta, 2)]);

        $this->info('Next: php artisan inventory:snapshots:rebuild <start_date> --with-vans, then php artisan accounting:reconcile-stock-gl');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
