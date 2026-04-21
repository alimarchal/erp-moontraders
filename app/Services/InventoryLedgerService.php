<?php

namespace App\Services;

use App\Models\DailyInventorySnapshot;
use App\Models\InventoryLedgerEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryLedgerService
{
    /**
     * Record a purchase (GRN) - stock coming INTO warehouse
     */
    public function recordPurchase(
        int $productId,
        int $warehouseId,
        float $quantity,
        float $unitCost,
        int $grnId,
        string $date,
        ?string $notes = null,
        ?int $batchId = null
    ): InventoryLedgerEntry {
        return $this->createEntry([
            'date' => $date,
            'transaction_type' => InventoryLedgerEntry::TYPE_PURCHASE,
            'product_id' => $productId,
            'stock_batch_id' => $batchId,
            'warehouse_id' => $warehouseId,
            'vehicle_id' => null,
            'employee_id' => null,
            'goods_receipt_note_id' => $grnId,
            'debit_qty' => $quantity,  // IN = Debit
            'credit_qty' => 0,
            'unit_cost' => $unitCost,
            'notes' => $notes,
        ]);
    }

    /**
     * Record a goods issue (transfer from warehouse to vehicle)
     * Creates TWO entries: credit from warehouse, debit to vehicle (double-entry)
     */
    public function recordIssue(
        int $productId,
        int $warehouseId,
        int $vehicleId,
        ?int $employeeId,
        float $quantity,
        float $unitCost,
        int $goodsIssueId,
        string $date,
        ?string $notes = null,
        ?int $batchId = null
    ): array {
        // Entry 1: Credit from warehouse (stock OUT)
        $warehouseEntry = $this->createEntry([
            'date' => $date,
            'transaction_type' => InventoryLedgerEntry::TYPE_TRANSFER_OUT,
            'product_id' => $productId,
            'stock_batch_id' => $batchId,
            'warehouse_id' => $warehouseId,
            'vehicle_id' => null,
            'employee_id' => $employeeId,
            'goods_issue_id' => $goodsIssueId,
            'debit_qty' => 0,
            'credit_qty' => $quantity,  // OUT = Credit
            'unit_cost' => $unitCost,
            'notes' => $notes.' (Warehouse OUT)',
        ]);

        // Entry 2: Debit to vehicle (stock IN)
        $vehicleEntry = $this->createEntry([
            'date' => $date,
            'transaction_type' => InventoryLedgerEntry::TYPE_TRANSFER_IN,
            'product_id' => $productId,
            'stock_batch_id' => $batchId,
            'warehouse_id' => null,
            'vehicle_id' => $vehicleId,
            'employee_id' => $employeeId,
            'goods_issue_id' => $goodsIssueId,
            'debit_qty' => $quantity,  // IN = Debit
            'credit_qty' => 0,
            'unit_cost' => $unitCost,
            'notes' => $notes.' (Vehicle IN)',
        ]);

        return [
            'warehouse_entry' => $warehouseEntry,
            'vehicle_entry' => $vehicleEntry,
        ];
    }

    /**
     * Record a sale - stock leaving vehicle (sold to customer)
     */
    public function recordSale(
        int $productId,
        int $vehicleId,
        ?int $employeeId,
        float $quantity,
        float $unitCost,
        int $settlementId,
        string $date,
        ?string $notes = null,
        ?int $batchId = null
    ): InventoryLedgerEntry {
        return $this->createEntry([
            'date' => $date,
            'transaction_type' => InventoryLedgerEntry::TYPE_SALE,
            'product_id' => $productId,
            'stock_batch_id' => $batchId,
            'warehouse_id' => null,
            'vehicle_id' => $vehicleId,
            'employee_id' => $employeeId,
            'sales_settlement_id' => $settlementId,
            'debit_qty' => 0,
            'credit_qty' => $quantity,  // OUT = Credit (sold)
            'unit_cost' => $unitCost,
            'notes' => $notes,
        ]);
    }

    /**
     * Record a return - stock coming from customer, going back to warehouse
     * Creates TWO entries: credit from vehicle (return out), debit to warehouse (return in)
     */
    public function recordReturn(
        int $productId,
        int $warehouseId,
        int $vehicleId,
        ?int $employeeId,
        float $quantity,
        float $unitCost,
        int $settlementId,
        string $date,
        ?string $notes = null,
        ?int $batchId = null
    ): array {
        // Entry 1: Credit from vehicle (stock leaving van - returned goods)
        $vehicleEntry = $this->createEntry([
            'date' => $date,
            'transaction_type' => InventoryLedgerEntry::TYPE_RETURN,
            'product_id' => $productId,
            'stock_batch_id' => $batchId,
            'warehouse_id' => null,
            'vehicle_id' => $vehicleId,
            'employee_id' => $employeeId,
            'sales_settlement_id' => $settlementId,
            'debit_qty' => 0,
            'credit_qty' => $quantity,  // OUT from vehicle = Credit
            'unit_cost' => $unitCost,
            'notes' => $notes.' (Return - Vehicle OUT)',
        ]);

        // Entry 2: Debit to warehouse (stock entering warehouse)
        $warehouseEntry = $this->createEntry([
            'date' => $date,
            'transaction_type' => InventoryLedgerEntry::TYPE_RETURN,
            'product_id' => $productId,
            'stock_batch_id' => $batchId,
            'warehouse_id' => $warehouseId,
            'vehicle_id' => null,
            'employee_id' => $employeeId,
            'sales_settlement_id' => $settlementId,
            'debit_qty' => $quantity,  // IN to warehouse = Debit
            'credit_qty' => 0,
            'unit_cost' => $unitCost,
            'notes' => $notes.' (Return - Warehouse IN)',
        ]);

        return [
            'vehicle_entry' => $vehicleEntry,
            'warehouse_entry' => $warehouseEntry,
        ];
    }

    /**
     * Record a shortage - stock lost from vehicle (write-off)
     */
    public function recordShortage(
        int $productId,
        int $vehicleId,
        ?int $employeeId,
        float $quantity,
        float $unitCost,
        int $settlementId,
        string $date,
        ?string $notes = null,
        ?int $batchId = null
    ): InventoryLedgerEntry {
        return $this->createEntry([
            'date' => $date,
            'transaction_type' => InventoryLedgerEntry::TYPE_SHORTAGE,
            'product_id' => $productId,
            'stock_batch_id' => $batchId,
            'warehouse_id' => null,
            'vehicle_id' => $vehicleId,
            'employee_id' => $employeeId,
            'sales_settlement_id' => $settlementId,
            'debit_qty' => 0,
            'credit_qty' => $quantity,  // OUT = Credit (lost)
            'unit_cost' => $unitCost,
            'notes' => $notes,
        ]);
    }

    /**
     * Record an adjustment (manual correction)
     */
    public function recordAdjustment(
        int $productId,
        ?int $warehouseId,
        ?int $vehicleId,
        float $debitQty,
        float $creditQty,
        float $unitCost,
        string $date,
        ?string $notes = null,
        ?int $batchId = null,
        ?int $stockAdjustmentId = null
    ): InventoryLedgerEntry {
        return $this->createEntry([
            'date' => $date,
            'transaction_type' => InventoryLedgerEntry::TYPE_ADJUSTMENT,
            'product_id' => $productId,
            'stock_batch_id' => $batchId,
            'warehouse_id' => $warehouseId,
            'vehicle_id' => $vehicleId,
            'employee_id' => null,
            'stock_adjustment_id' => $stockAdjustmentId,
            'debit_qty' => $debitQty,
            'credit_qty' => $creditQty,
            'unit_cost' => $unitCost,
            'notes' => $notes,
        ]);
    }

    /**
     * Create a ledger entry with running balance calculation
     */
    protected function createEntry(array $data): InventoryLedgerEntry
    {
        // Calculate running balance
        // Calculate running balance (GLOBAL per product, not per location)
        $runningBalance = InventoryLedgerEntry::calculateRunningBalance(
            $data['product_id'],
            null, // warehouse_id
            null  // vehicle_id
        );

        // Apply this transaction's effect
        $netChange = ($data['debit_qty'] ?? 0) - ($data['credit_qty'] ?? 0);
        $runningBalance += $netChange;

        // Calculate total value
        $totalQty = max($data['debit_qty'] ?? 0, $data['credit_qty'] ?? 0);
        $totalValue = $totalQty * ($data['unit_cost'] ?? 0);

        return InventoryLedgerEntry::create(array_merge($data, [
            'running_balance' => $runningBalance,
            'total_value' => $totalValue,
            // created_by and updated_by are handled automatically by UserTracking trait
        ]));
    }

    /**
     * Get ledger entries for a product with filters
     */
    public function getLedger(
        int $productId,
        ?int $warehouseId = null,
        ?int $vehicleId = null,
        ?int $employeeId = null,
        ?int $batchId = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $transactionType = null
    ) {
        $query = InventoryLedgerEntry::with(['product', 'warehouse', 'vehicle', 'employee', 'stockBatch', 'goodsReceiptNote', 'goodsIssue', 'salesSettlement'])
            ->where('product_id', $productId)
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc');

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }
        if ($vehicleId) {
            $query->where('vehicle_id', $vehicleId);
        }
        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }
        if ($batchId) {
            $query->where('stock_batch_id', $batchId);
        }
        if ($startDate && $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        }
        if ($transactionType) {
            $query->where('transaction_type', $transactionType);
        }

        return $query->get();
    }

    /**
     * Create daily inventory snapshots for all products at all locations
     */
    public function createDailySnapshots(?string $date = null): int
    {
        $snapshotDate = $date ?? now()->toDateString();
        $count = 0;

        try {
            DB::beginTransaction();

            // Read closing warehouse stock directly from current_stock_by_batch.
            // This table is always up-to-date and stores total_value at exact precision —
            // no multiplication needed, so no floating-point rounding loss.
            $rows = DB::table('current_stock_by_batch as csb')
                ->join('products as p', 'csb.product_id', '=', 'p.id')
                ->where('csb.status', 'active')
                ->whereNull('p.deleted_at')
                ->select(
                    'csb.product_id',
                    'csb.warehouse_id',
                    DB::raw('SUM(csb.quantity_on_hand) as quantity_on_hand'),
                    DB::raw('SUM(csb.total_value) / NULLIF(SUM(csb.quantity_on_hand), 0) as average_cost'),
                    DB::raw('SUM(csb.total_value) as total_value')
                )
                ->groupBy('csb.product_id', 'csb.warehouse_id')
                ->having(DB::raw('SUM(csb.quantity_on_hand)'), '>', 0)
                ->get();

            foreach ($rows as $row) {
                DailyInventorySnapshot::updateOrCreate(
                    [
                        'date' => $snapshotDate,
                        'product_id' => $row->product_id,
                        'warehouse_id' => $row->warehouse_id,
                        'vehicle_id' => null,
                    ],
                    [
                        'quantity_on_hand' => $row->quantity_on_hand,
                        'average_cost' => $row->average_cost,
                        'total_value' => $row->total_value,
                    ]
                );

                $count++;
            }

            DB::commit();
            Log::info("Created {$count} daily inventory snapshots for {$snapshotDate}");

            return $count;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create inventory snapshots: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Validate double-entry for a goods issue
     * Sum of debits should equal sum of credits for the same goods_issue_id
     */
    public function validateDoubleEntry(int $goodsIssueId): bool
    {
        $totals = InventoryLedgerEntry::where('goods_issue_id', $goodsIssueId)
            ->selectRaw('SUM(debit_qty) as total_debit, SUM(credit_qty) as total_credit')
            ->first();

        // Debits should equal credits (within small tolerance for floating point)
        return abs(($totals->total_debit ?? 0) - ($totals->total_credit ?? 0)) < 0.001;
    }
}
