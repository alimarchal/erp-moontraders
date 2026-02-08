<?php

namespace App\Console\Commands;

use App\Models\GoodsIssue;
use App\Models\GoodsReceiptNote;
use App\Models\InventoryLedgerEntry;
use App\Models\SalesSettlement;
use App\Models\StockBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BackfillInventoryLedger extends Command
{
    protected $signature = 'inventory:backfill-ledger
                            {--fresh : Clear existing ledger entries before backfilling}
                            {--grn-only : Only process GRN entries}
                            {--gi-only : Only process Goods Issue entries}
                            {--ss-only : Only process Sales Settlement entries}
                            {--from= : Start date (Y-m-d)}
                            {--to= : End date (Y-m-d)}';

    protected $description = 'Backfill the inventory ledger with existing GRN, Goods Issue, and Sales Settlement data';

    protected int $grnCount = 0;

    protected int $giCount = 0;

    protected int $ssCount = 0;

    public function handle(): int
    {
        $this->info('=== Inventory Ledger Backfill ===');
        $this->newLine();

        if ($this->option('fresh')) {
            if ($this->confirm('This will DELETE all existing ledger entries. Continue?')) {
                $this->warn('Clearing existing ledger entries...');
                InventoryLedgerEntry::truncate();
                $this->info('Ledger entries cleared.');
            } else {
                $this->info('Backfill cancelled.');

                return self::SUCCESS;
            }
        }

        $fromDate = $this->option('from');
        $toDate = $this->option('to');

        if ($fromDate || $toDate) {
            $this->info("Date range: {$fromDate} to {$toDate}");
        }

        try {
            DB::beginTransaction();

            // Process in chronological order for accurate running balances
            $processAll = !$this->option('grn-only') && !$this->option('gi-only') && !$this->option('ss-only');

            if ($processAll || $this->option('grn-only')) {
                $this->processGRNs($fromDate, $toDate);
            }

            if ($processAll || $this->option('gi-only')) {
                $this->processGoodsIssues($fromDate, $toDate);
            }

            if ($processAll || $this->option('ss-only')) {
                $this->processSalesSettlements($fromDate, $toDate);
            }

            $this->recalculateRunningBalances();

            DB::commit();

            $this->newLine();
            $this->info('=== Backfill Summary ===');
            $this->table(
                ['Document Type', 'Entries Created'],
                [
                    ['GRN (Purchase)', $this->grnCount],
                    ['Goods Issue', $this->giCount],
                    ['Sales Settlement', $this->ssCount],
                    ['TOTAL', $this->grnCount + $this->giCount + $this->ssCount],
                ]
            );

            Log::info('Inventory ledger backfill completed', [
                'grn_entries' => $this->grnCount,
                'gi_entries' => $this->giCount,
                'ss_entries' => $this->ssCount,
            ]);

            return self::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Backfill failed: ' . $e->getMessage());
            Log::error('Inventory ledger backfill failed', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }
    }

    protected function recalculateRunningBalances(): void
    {
        $this->info('Recalculating running balances...');

        $productIds = InventoryLedgerEntry::distinct()->pluck('product_id');
        $bar = $this->output->createProgressBar($productIds->count());

        foreach ($productIds as $productId) {
            $runningBalance = 0;
            $entries = InventoryLedgerEntry::where('product_id', $productId)
                ->orderBy('date')
                ->orderBy('id')
                ->get();

            foreach ($entries as $entry) {
                // Debit adds to stock, Credit subtracts from stock
                $runningBalance += ($entry->debit_qty - $entry->credit_qty);

                // Only update if changed to save DB calls
                if ($entry->running_balance != $runningBalance) {
                    $entry->running_balance = $runningBalance;
                    $entry->saveQuietly(); // Avoid timestamp updates
                }
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    protected function processGRNs(?string $fromDate, ?string $toDate): void
    {
        $this->info('Processing Goods Receipt Notes (Purchases)...');

        $query = GoodsReceiptNote::with(['items.product'])
            ->whereIn('status', ['posted', 'completed']);

        if ($fromDate) {
            $query->where('receipt_date', '>=', $fromDate);
        }
        if ($toDate) {
            $query->where('receipt_date', '<=', $toDate);
        }

        $grns = $query->orderBy('receipt_date')->orderBy('id')->get();
        $bar = $this->output->createProgressBar($grns->count());

        foreach ($grns as $grn) {
            foreach ($grn->items as $item) {
                $this->createPurchaseEntry($grn, $item);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    protected function createPurchaseEntry($grn, $item): void
    {
        // Find the stock batch created from this GRN item (via batch_number)
        $stockBatch = null;
        if ($item->batch_number) {
            $stockBatch = StockBatch::where('batch_code', $item->batch_number)->first();
        }

        // If no batch_number, try to find batch by product + receipt date
        if (!$stockBatch) {
            $stockBatch = StockBatch::where('product_id', $item->product_id)
                ->whereDate('receipt_date', $grn->receipt_date)
                ->first();
        }

        // Avoid duplicates
        $existsQuery = InventoryLedgerEntry::where('goods_receipt_note_id', $grn->id)
            ->where('product_id', $item->product_id)
            ->where('transaction_type', InventoryLedgerEntry::TYPE_PURCHASE);

        if ($stockBatch) {
            $existsQuery->where('stock_batch_id', $stockBatch->id);
        }

        if ($existsQuery->exists()) {
            return;
        }

        $qty = $item->quantity_received ?? $item->qty_in_stock_uom ?? 0;
        $unitCost = $item->unit_cost ?? 0;
        $sellingPrice = $stockBatch?->selling_price ?? $item->selling_price ?? 0;

        if ($qty <= 0) {
            return;
        }

        InventoryLedgerEntry::create([
            'date' => $grn->receipt_date,
            'transaction_type' => InventoryLedgerEntry::TYPE_PURCHASE,
            'product_id' => $item->product_id,
            'stock_batch_id' => $stockBatch?->id,
            'warehouse_id' => $grn->warehouse_id,
            'goods_receipt_note_id' => $grn->id,
            'debit_qty' => $qty,
            'credit_qty' => 0,
            'unit_cost' => $unitCost,
            'selling_price' => $sellingPrice,
            'total_value' => $qty * $unitCost,
            'notes' => "GRN {$grn->grn_number}" . ($stockBatch ? " - Batch {$stockBatch->batch_code}" : ''),
        ]);

        $this->grnCount++;
    }

    protected function processGoodsIssues(?string $fromDate, ?string $toDate): void
    {
        $this->info('Processing Goods Issues (Transfers to Van)...');

        $query = GoodsIssue::with(['items.product'])
            ->whereIn('status', ['posted', 'completed', 'issued']);

        if ($fromDate) {
            $query->where('issue_date', '>=', $fromDate);
        }
        if ($toDate) {
            $query->where('issue_date', '<=', $toDate);
        }

        $issues = $query->orderBy('issue_date')->orderBy('id')->get();
        $bar = $this->output->createProgressBar($issues->count());

        foreach ($issues as $issue) {
            foreach ($issue->items as $item) {
                $this->createIssueEntries($issue, $item);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    protected function createIssueEntries($issue, $item): void
    {
        // Avoid duplicates
        $exists = InventoryLedgerEntry::where('goods_issue_id', $issue->id)
            ->where('product_id', $item->product_id)
            ->where('transaction_type', InventoryLedgerEntry::TYPE_TRANSFER_OUT)
            ->exists();

        if ($exists) {
            return;
        }

        $qty = $item->quantity_issued ?? 0;
        $unitCost = $item->unit_cost ?? 0;
        $sellingPrice = $item->selling_price ?? 0;

        if ($qty <= 0) {
            return;
        }

        // Warehouse OUT (Credit)
        InventoryLedgerEntry::create([
            'date' => $issue->issue_date,
            'transaction_type' => InventoryLedgerEntry::TYPE_TRANSFER_OUT,
            'product_id' => $item->product_id,
            'warehouse_id' => $issue->warehouse_id,
            'employee_id' => $issue->employee_id,
            'goods_issue_id' => $issue->id,
            'debit_qty' => 0,
            'credit_qty' => $qty,
            'unit_cost' => $unitCost,
            'selling_price' => $sellingPrice,
            'total_value' => $qty * $unitCost,
            'notes' => "GI {$issue->issue_number} - Warehouse OUT",
        ]);

        // Vehicle IN (Debit)
        InventoryLedgerEntry::create([
            'date' => $issue->issue_date,
            'transaction_type' => InventoryLedgerEntry::TYPE_TRANSFER_IN,
            'product_id' => $item->product_id,
            'vehicle_id' => $issue->vehicle_id,
            'employee_id' => $issue->employee_id,
            'goods_issue_id' => $issue->id,
            'debit_qty' => $qty,
            'credit_qty' => 0,
            'unit_cost' => $unitCost,
            'selling_price' => $sellingPrice,
            'total_value' => $qty * $unitCost,
            'notes' => "GI {$issue->issue_number} - Vehicle IN",
        ]);

        $this->giCount += 2; // Two entries per issue (double-entry)
    }

    protected function processSalesSettlements(?string $fromDate, ?string $toDate): void
    {
        $this->info('Processing Sales Settlements...');

        $query = SalesSettlement::with(['items.product', 'items.batches'])
            ->whereIn('status', ['posted', 'completed']);

        if ($fromDate) {
            $query->where('settlement_date', '>=', $fromDate);
        }
        if ($toDate) {
            $query->where('settlement_date', '<=', $toDate);
        }

        $settlements = $query->orderBy('settlement_date')->orderBy('id')->get();
        $bar = $this->output->createProgressBar($settlements->count());

        foreach ($settlements as $settlement) {
            foreach ($settlement->items as $item) {
                $this->createSettlementEntries($settlement, $item);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    protected function createSettlementEntries($settlement, $item): void
    {
        $unitCost = $item->unit_cost ?? 0;
        $sellingPrice = $item->unit_selling_price ?? 0;

        // SALES (Credit - stock leaving vehicle)
        $soldQty = $item->quantity_sold ?? 0;
        if ($soldQty > 0) {
            $exists = InventoryLedgerEntry::where('sales_settlement_id', $settlement->id)
                ->where('product_id', $item->product_id)
                ->where('transaction_type', InventoryLedgerEntry::TYPE_SALE)
                ->exists();

            if (!$exists) {
                InventoryLedgerEntry::create([
                    'date' => $settlement->settlement_date,
                    'transaction_type' => InventoryLedgerEntry::TYPE_SALE,
                    'product_id' => $item->product_id,
                    'vehicle_id' => $settlement->vehicle_id,
                    'employee_id' => $settlement->employee_id,
                    'sales_settlement_id' => $settlement->id,
                    'debit_qty' => 0,
                    'credit_qty' => $soldQty,
                    'unit_cost' => $unitCost,
                    'selling_price' => $sellingPrice,
                    'total_value' => $soldQty * $unitCost,
                    'notes' => "Sale - SS {$settlement->settlement_number}",
                ]);
                $this->ssCount++;
            }
        }

        // RETURNS (Credit from vehicle, Debit to warehouse)
        $returnQty = $item->quantity_returned ?? 0;
        if ($returnQty > 0) {
            $exists = InventoryLedgerEntry::where('sales_settlement_id', $settlement->id)
                ->where('product_id', $item->product_id)
                ->where('transaction_type', InventoryLedgerEntry::TYPE_RETURN)
                ->exists();

            if (!$exists) {
                // Vehicle OUT (Credit - returning goods)
                InventoryLedgerEntry::create([
                    'date' => $settlement->settlement_date,
                    'transaction_type' => InventoryLedgerEntry::TYPE_RETURN,
                    'product_id' => $item->product_id,
                    'vehicle_id' => $settlement->vehicle_id,
                    'employee_id' => $settlement->employee_id,
                    'sales_settlement_id' => $settlement->id,
                    'debit_qty' => 0,
                    'credit_qty' => $returnQty,
                    'unit_cost' => $unitCost,
                    'total_value' => $returnQty * $unitCost,
                    'notes' => "Return - SS {$settlement->settlement_number} (Vehicle OUT)",
                ]);

                // Warehouse IN (Debit - receiving returned goods)
                $warehouseId = $settlement->warehouse_id ?? $settlement->goodsIssue?->warehouse_id ?? 1;
                InventoryLedgerEntry::create([
                    'date' => $settlement->settlement_date,
                    'transaction_type' => InventoryLedgerEntry::TYPE_RETURN,
                    'product_id' => $item->product_id,
                    'warehouse_id' => $warehouseId,
                    'employee_id' => $settlement->employee_id,
                    'sales_settlement_id' => $settlement->id,
                    'debit_qty' => $returnQty,
                    'credit_qty' => 0,
                    'unit_cost' => $unitCost,
                    'total_value' => $returnQty * $unitCost,
                    'notes' => "Return - SS {$settlement->settlement_number} (Warehouse IN)",
                ]);
                $this->ssCount += 2;
            }
        }

        // SHORTAGES (Credit - stock lost from vehicle)
        $shortageQty = $item->quantity_shortage ?? 0;
        if ($shortageQty > 0) {
            $exists = InventoryLedgerEntry::where('sales_settlement_id', $settlement->id)
                ->where('product_id', $item->product_id)
                ->where('transaction_type', InventoryLedgerEntry::TYPE_SHORTAGE)
                ->exists();

            if (!$exists) {
                InventoryLedgerEntry::create([
                    'date' => $settlement->settlement_date,
                    'transaction_type' => InventoryLedgerEntry::TYPE_SHORTAGE,
                    'product_id' => $item->product_id,
                    'vehicle_id' => $settlement->vehicle_id,
                    'employee_id' => $settlement->employee_id,
                    'sales_settlement_id' => $settlement->id,
                    'debit_qty' => 0,
                    'credit_qty' => $shortageQty,
                    'unit_cost' => $unitCost,
                    'total_value' => $shortageQty * $unitCost,
                    'notes' => "Shortage - SS {$settlement->settlement_number}",
                ]);
                $this->ssCount++;
            }
        }
    }
}
