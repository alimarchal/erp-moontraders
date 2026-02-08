<?php

use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\InventoryLedgerEntry;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\StockBatch;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\InventoryLedgerService;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    actingAs(User::factory()->create());
});

describe('InventoryLedgerEntry Model', function () {
    it('can create a ledger entry with debit/credit', function () {
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $batch = StockBatch::factory()->create(['product_id' => $product->id]);

        $entry = InventoryLedgerEntry::create([
            'date' => now()->toDateString(),
            'transaction_type' => 'purchase',
            'product_id' => $product->id,
            'stock_batch_id' => $batch->id,
            'warehouse_id' => $warehouse->id,
            'debit_qty' => 100,
            'credit_qty' => 0,
            'unit_cost' => 50.00,
            'total_value' => 5000.00,
            'running_balance' => 100,
        ]);

        expect($entry)->toBeInstanceOf(InventoryLedgerEntry::class);
        expect($entry->product_id)->toBe($product->id);
        expect($entry->warehouse_id)->toBe($warehouse->id);
        expect($entry->stock_batch_id)->toBe($batch->id);
        expect((float) $entry->debit_qty)->toBe(100.0);
        expect((float) $entry->credit_qty)->toBe(0.0);
        expect($entry->is_inward)->toBeTrue();
        expect($entry->is_outward)->toBeFalse();
    });

    it('has relationships including batch', function () {
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $employee = Employee::factory()->create();
        $batch = StockBatch::factory()->create(['product_id' => $product->id]);

        $entry = InventoryLedgerEntry::create([
            'date' => now()->toDateString(),
            'transaction_type' => 'issue',
            'product_id' => $product->id,
            'stock_batch_id' => $batch->id,
            'warehouse_id' => $warehouse->id,
            'employee_id' => $employee->id,
            'debit_qty' => 0,
            'credit_qty' => 50,
            'unit_cost' => 50.00,
            'total_value' => 2500.00,
            'running_balance' => 50,
        ]);

        expect($entry->product)->not->toBeNull();
        expect($entry->warehouse)->not->toBeNull();
        expect($entry->employee)->not->toBeNull();
        expect($entry->stockBatch)->not->toBeNull();
    });
});

describe('InventoryLedgerService Double-Entry', function () {
    it('can record a purchase entry with debit', function () {
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $supplier = \App\Models\Supplier::factory()->create();
        $grn = \App\Models\GoodsReceiptNote::factory()->create([
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
        ]);
        $batch = StockBatch::factory()->create(['product_id' => $product->id]);

        $service = app(InventoryLedgerService::class);
        $entry = $service->recordPurchase(
            $product->id,
            $warehouse->id,
            100,
            50.00,
            $grn->id,
            now()->toDateString(),
            'Test purchase',
            $batch->id
        );

        expect((float) $entry->debit_qty)->toBe(100.0);  // IN = Debit
        expect((float) $entry->credit_qty)->toBe(0.0);
        expect($entry->transaction_type)->toBe('purchase');
        expect($entry->stock_batch_id)->toBe($batch->id);
        assertDatabaseHas('inventory_ledger_entries', [
            'product_id' => $product->id,
            'transaction_type' => 'purchase',
            'stock_batch_id' => $batch->id,
        ]);
    });

    it('can record an issue with double entries (warehouse credit, vehicle debit)', function () {
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $employee = Employee::factory()->create();
        $gi = GoodsIssue::factory()->create([
            'warehouse_id' => $warehouse->id,
            'vehicle_id' => $vehicle->id,
            'employee_id' => $employee->id,
        ]);
        $batch = StockBatch::factory()->create(['product_id' => $product->id]);

        $service = app(InventoryLedgerService::class);
        $entries = $service->recordIssue(
            $product->id,
            $warehouse->id,
            $vehicle->id,
            $employee->id,
            50,
            50.00,
            $gi->id,
            now()->toDateString(),
            'Test issue',
            $batch->id
        );

        expect($entries)->toHaveKeys(['warehouse_entry', 'vehicle_entry']);

        // Warehouse entry: Credit (OUT)
        expect((float) $entries['warehouse_entry']->credit_qty)->toBe(50.0);
        expect((float) $entries['warehouse_entry']->debit_qty)->toBe(0.0);
        expect($entries['warehouse_entry']->transaction_type)->toBe('transfer_out');

        // Vehicle entry: Debit (IN)
        expect((float) $entries['vehicle_entry']->debit_qty)->toBe(50.0);
        expect((float) $entries['vehicle_entry']->credit_qty)->toBe(0.0);
        expect($entries['vehicle_entry']->transaction_type)->toBe('transfer_in');
    });

    it('validates double-entry balance (debits = credits for goods issue)', function () {
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $employee = Employee::factory()->create();
        $gi = GoodsIssue::factory()->create([
            'warehouse_id' => $warehouse->id,
            'vehicle_id' => $vehicle->id,
            'employee_id' => $employee->id,
        ]);

        $service = app(InventoryLedgerService::class);
        $service->recordIssue(
            $product->id,
            $warehouse->id,
            $vehicle->id,
            $employee->id,
            50,
            50.00,
            $gi->id,
            now()->toDateString(),
            'Test issue'
        );

        // Validate double-entry balance
        $isBalanced = $service->validateDoubleEntry($gi->id);
        expect($isBalanced)->toBeTrue();
    });

    it('can record a sale entry with credit', function () {
        $product = Product::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $employee = Employee::factory()->create();
        $settlement = SalesSettlement::factory()->create([
            'vehicle_id' => $vehicle->id,
            'employee_id' => $employee->id,
        ]);

        $service = app(InventoryLedgerService::class);
        $entry = $service->recordSale(
            $product->id,
            $vehicle->id,
            $employee->id,
            30,
            50.00,
            $settlement->id,
            now()->toDateString(),
            'Test sale'
        );

        expect((float) $entry->credit_qty)->toBe(30.0);  // OUT = Credit
        expect((float) $entry->debit_qty)->toBe(0.0);
        expect($entry->transaction_type)->toBe('sale');
    });

    it('calculates opening balance correctly with debit/credit', function () {
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();

        // Create initial debit entry
        InventoryLedgerEntry::create([
            'date' => now()->subDays(2)->toDateString(),
            'transaction_type' => 'purchase',
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'debit_qty' => 100,
            'credit_qty' => 0,
            'unit_cost' => 50.00,
            'total_value' => 5000.00,
            'running_balance' => 100,
        ]);

        // Create outgoing credit entry
        InventoryLedgerEntry::create([
            'date' => now()->subDay()->toDateString(),
            'transaction_type' => 'transfer_out',
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'debit_qty' => 0,
            'credit_qty' => 30,
            'unit_cost' => 50.00,
            'total_value' => 1500.00,
            'running_balance' => 70,
        ]);

        // Calculate opening balance: SUM(debit) - SUM(credit) = 100 - 30 = 70
        $balance = InventoryLedgerEntry::calculateRunningBalance($product->id, $warehouse->id);

        expect($balance)->toBe(70.0);
    });
});

describe('Inventory Ledger Report Route', function () {
    it('inventory ledger route exists and is named correctly', function () {
        expect(route('reports.inventory-ledger.index'))->toContain('inventory-ledger');
    });
});
