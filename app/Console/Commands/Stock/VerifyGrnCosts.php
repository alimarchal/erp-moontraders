<?php

namespace App\Console\Commands\Stock;

use App\Notifications\GrnCostDriftDetected;
use App\Services\GrnCostDriftService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class VerifyGrnCosts extends Command
{
    protected $signature = 'inventory:verify-costs';

    protected $description = 'Check that every posted GRN line, its receipt movement, batch and everything later issued, '
        .'sold or returned from the batch carry the same cost. Report only — repair with stock:repair-grn-cost-drift.';

    public function handle(GrnCostDriftService $service): int
    {
        $drifted = $service->detect();

        if ($drifted->isEmpty()) {
            $this->info('Every GRN line agrees with its receipt movement, batch and issues.');

            return self::SUCCESS;
        }

        $lines = $drifted->map(fn (object $drift) => [
            'grn_number' => $drift->grn_number,
            'product_id' => $drift->product_id,
            'stock_batch_id' => $drift->stock_batch_id,
            'problems' => implode(', ', $drift->problems),
        ])->all();

        $this->table(['GRN', 'Product', 'Batch', 'Problems'], array_map('array_values', $lines));

        Log::warning('GRN cost check found lines out of step', ['lines' => $lines]);

        $recipient = config('backup.notifications.mail.to');

        if ($recipient) {
            try {
                Notification::route('mail', $recipient)->notify(new GrnCostDriftDetected($lines));
            } catch (\Throwable $e) {
                Log::error('Could not email the GRN cost alert: '.$e->getMessage());
                $this->warn('Could not send the alert email: '.$e->getMessage());
            }
        }

        $this->error('GRN costs do not agree. Run: php artisan stock:repair-grn-cost-drift --dry-run');

        return self::FAILURE;
    }
}
