<?php

namespace App\Console\Commands\Accounting;

use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Services\AccountingService;
use App\Services\InventoryService;
use App\Services\StockReceivedNotBilledService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClearStockReceivedNotBilled extends Command
{
    protected $signature = 'accounting:clear-stock-received-not-billed
        {supplier : Supplier id}
        {--up-to= : Last date (Y-m-d) whose GRNs are all invoiced in the ledger register. Defaults to today}
        {--dry-run : Report what would be posted without saving}';

    protected $description = 'Post what is left on Stock Received But Not Billed (2142) for one supplier, up to a date '
        .'by which every GRN has its invoice, into Purchase Price Difference (5274). That remainder is the difference '
        .'between what the supplier invoiced and what the GRNs recorded.';

    public function handle(StockReceivedNotBilledService $receivedNotBilled): int
    {
        $supplierId = (int) $this->argument('supplier');
        $supplierName = DB::table('suppliers')->where('id', $supplierId)->value('supplier_name');

        if (! $supplierName) {
            $this->error("Supplier {$supplierId} not found.");

            return self::FAILURE;
        }

        $upTo = $this->option('up-to') ?? now()->toDateString();

        if (! Carbon::hasFormat($upTo, 'Y-m-d')) {
            $this->error("--up-to must be a date in Y-m-d format, got \"{$upTo}\".");

            return self::FAILURE;
        }

        $receivedNotBilledId = ChartOfAccount::where('account_code', InventoryService::STOCK_RECEIVED_NOT_BILLED_CODE)->value('id');
        $priceDifferenceId = ChartOfAccount::where('account_code', StockReceivedNotBilledService::PURCHASE_PRICE_DIFFERENCE_CODE)->value('id');

        if (! $receivedNotBilledId || ! $priceDifferenceId) {
            $this->error('Accounts 2142 (Stock Received But Not Billed) and 5274 (Purchase Price Difference) must both exist. Run php artisan migrate.');

            return self::FAILURE;
        }

        $row = $receivedNotBilled->balancesBySupplier($upTo)->get($supplierId)
            ?? ['received' => 0.0, 'billed' => 0.0, 'cleared' => 0.0, 'balance' => 0.0];

        $this->table(['Up to '.$upTo, 'Amount'], [
            ['Received on GRNs', number_format($row['received'], 2)],
            ['Billed in the ledger register', number_format($row['billed'], 2)],
            ['Already cleared', number_format($row['cleared'], 2)],
            ['Left on 2142', number_format($row['balance'], 2)],
        ]);

        if (abs($row['balance']) < 0.01) {
            $this->info("Nothing left on 2142 for {$supplierName} up to {$upTo}.");

            return self::SUCCESS;
        }

        $amount = abs($row['balance']);
        $this->line($row['balance'] > 0
            ? "GRNs recorded {$amount} more than {$supplierName} invoiced: Dr 2142 / Cr 5274."
            : "{$supplierName} invoiced {$amount} more than the GRNs recorded: Dr 5274 / Cr 2142.");

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN — nothing was posted. Check that every GRN up to this date has its invoice before running it for real.');

            return self::SUCCESS;
        }

        $lines = $row['balance'] > 0
            ? [[$receivedNotBilledId, $amount, 0], [$priceDifferenceId, 0, $amount]]
            : [[$priceDifferenceId, $amount, 0], [$receivedNotBilledId, 0, $amount]];

        $result = app(AccountingService::class)->createJournalEntry([
            'entry_date' => $upTo,
            'reference' => StockReceivedNotBilledService::clearingReference($supplierId, $upTo),
            'description' => "Purchase price difference - {$supplierName} - invoices against GRNs up to {$upTo}",
            'lines' => array_map(fn (array $line) => [
                'account_id' => $line[0],
                'debit' => $line[1],
                'credit' => $line[2],
                'description' => "Invoice vs GRN difference - {$supplierName}",
                'cost_center_id' => CostCenter::where('code', 'CC006')->value('id'),
            ], $lines),
            'auto_post' => true,
        ]);

        if (! $result['success']) {
            $this->error($result['message']);

            return self::FAILURE;
        }

        Log::info('Cleared Stock Received But Not Billed into Purchase Price Difference', [
            'supplier_id' => $supplierId,
            'up_to' => $upTo,
            'amount' => $row['balance'],
            'journal_entry_id' => $result['data']->id,
        ]);
        $this->info("Posted journal entry #{$result['data']->id}.");

        return self::SUCCESS;
    }
}
