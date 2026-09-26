<?php

namespace App\Console\Commands\Accounting;

use App\Notifications\StockLedgerOutOfStep;
use App\Services\InventoryGlAdjustmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ReconcileStockGl extends Command
{
    protected $signature = 'accounting:reconcile-stock-gl
        {--tolerance=5000 : Largest gap (Rs) treated as rounding and not reported}
        {--post : Post an adjusting entry that brings 1151 and 1155 to the stock value, against COGS}
        {--max=100000 : Refuse to --post a gap larger than this; a gap that size needs finding, not plugging}
        {--dry-run : With --post, show the entry without saving it}';

    protected $description = 'Compare Stock In Hand (1151) and Van Stock (1155) in the general ledger with the value '
        .'of the stock itself (current_stock_by_batch and van_stock_batches). Without --post it only reports, and emails '
        .'the backup recipient when a gap is larger than --tolerance.';

    public function handle(InventoryGlAdjustmentService $service): int
    {
        $gaps = $service->stockLedgerGaps();
        $names = [InventoryGlAdjustmentService::STOCK_IN_HAND => 'Stock In Hand', InventoryGlAdjustmentService::VAN_STOCK => 'Van Stock'];

        $this->table(['Account', 'General ledger', 'Stock value', 'Stock minus ledger'], collect($gaps)->map(fn (array $row, string $code) => [
            "{$code} {$names[$code]}",
            number_format($row['ledger'], 2),
            number_format($row['stock'], 2),
            number_format($row['gap'], 2),
        ])->values()->all());

        $largest = collect($gaps)->max(fn (array $row) => abs($row['gap']));

        if ($this->option('post')) {
            return $this->post($service, $largest);
        }

        if ($largest <= (float) $this->option('tolerance')) {
            $this->info('The ledger agrees with the stock within the tolerance.');

            return self::SUCCESS;
        }

        $this->error('The ledger and the stock are out of step. A document posted a journal entry at a different value than it moved stock.');
        Log::warning('Stock ledger out of step with stock value', $gaps);
        $this->email($gaps);

        return self::FAILURE;
    }

    private function post(InventoryGlAdjustmentService $service, float $largest): int
    {
        if ($largest < 0.01) {
            $this->info('Nothing to post.');

            return self::SUCCESS;
        }

        if ($largest > (float) $this->option('max')) {
            $this->error(sprintf('The gap of %s is above --max. Find the documents behind it before posting a true-up.', number_format($largest, 2)));

            return self::FAILURE;
        }

        DB::beginTransaction();

        try {
            $entry = $service->postTrueUp(now()->toDateString());
            $after = $service->stockLedgerGaps();

            if ($this->option('dry-run')) {
                DB::rollBack();
                $this->warn('DRY RUN — the true-up above was rolled back. Re-run without --dry-run to post it.');

                return self::SUCCESS;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Nothing was posted: '.$e->getMessage());

            return self::FAILURE;
        }

        Log::info('Posted stock ledger true-up', ['journal_entry_id' => $entry?->id, 'after' => $after]);
        $this->info(sprintf('Posted journal entry #%d. Stock minus ledger is now %s (1151) and %s (1155).',
            $entry?->id, number_format($after['1151']['gap'], 2), number_format($after['1155']['gap'], 2)));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, array{ledger: float, stock: float, gap: float}>  $gaps
     */
    private function email(array $gaps): void
    {
        $recipient = config('backup.notifications.mail.to');

        if (! $recipient) {
            return;
        }

        try {
            Notification::route('mail', $recipient)->notify(new StockLedgerOutOfStep($gaps));
        } catch (\Throwable $e) {
            Log::error('Could not email the stock ledger alert: '.$e->getMessage());
            $this->warn('Could not send the alert email: '.$e->getMessage());
        }
    }
}
