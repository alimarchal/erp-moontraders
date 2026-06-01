<?php

use App\Models\AccountingPeriod;
use App\Models\AccountType;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\Currency;
use App\Models\CurrentStock;
use App\Models\CurrentStockByBatch;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteItem;
use App\Models\JournalEntryDetail;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\StockValuationLayer;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DistributionService;
use App\Services\InventoryService;
use App\Services\SalesSettlementRevertService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;

/*
 * These tests assert that current_stock_by_batch.total_value always equals the
 * authoritative goods_receipt_note_items.total_cost (and therefore the GL debit
 * amount), eliminating float-precision drift between the Stock Report and GL.
 */

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'opening-stock-create', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'goods-receipt-note-create', 'guard_name' => 'web']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('opening-stock-create', 'goods-receipt-note-create');
    $this->actingAs($this->user);

    $currency = Currency::create([
        'currency_code' => 'PKR',
        'currency_name' => 'Pakistani Rupee',
        'currency_symbol' => 'Rs',
        'is_base_currency' => true,
    ]);

    AccountingPeriod::create([
        'name' => now()->format('F Y'),
        'start_date' => now()->startOfMonth(),
        'end_date' => now()->endOfMonth(),
        'status' => 'open',
    ]);

    CostCenter::create(['code' => 'CC006', 'name' => 'Warehouse & Inventory', 'is_active' => true]);

    $assetType = AccountType::create(['type_name' => 'Assets', 'report_group' => 'BalanceSheet', 'category' => 'Asset']);
    $equityType = AccountType::create(['type_name' => 'Equity', 'report_group' => 'BalanceSheet', 'category' => 'Equity']);

    $this->inventoryAccount = ChartOfAccount::create([
        'account_type_id' => $assetType->id,
        'currency_id' => $currency->id,
        'account_code' => '1151',
        'account_name' => 'Stock In Hand',
        'normal_balance' => 'debit',
        'is_active' => true,
    ]);

    ChartOfAccount::create([
        'account_type_id' => $equityType->id,
        'currency_id' => $currency->id,
        'account_code' => '3300',
        'account_name' => 'Opening Balance Equity',
        'normal_balance' => 'credit',
        'is_active' => true,
    ]);

    $this->supplier = Supplier::factory()->create(['disabled' => false]);
    $this->warehouse = Warehouse::factory()->create(['id' => 1, 'disabled' => false]);

    Uom::factory()->create(['id' => 24, 'uom_name' => 'Piece', 'symbol' => 'PCS', 'enabled' => true]);

    $this->product = Product::factory()->create([
        'product_code' => 'SKU-PREC-001',
        'supplier_id' => $this->supplier->id,
        'is_active' => true,
        'is_powder' => false,
    ]);
});

function makeOpeningStockFile(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $headers = ['SKU', 'Invoice Price', 'Retail Price', 'Total Inventory in Pieces'];

    foreach ($headers as $col => $header) {
        $sheet->setCellValue([$col + 1, 1], $header);
    }

    foreach ($rows as $rowIdx => $row) {
        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, $rowIdx + 2], $row[$header] ?? '');
        }
    }

    $tmp = tempnam(sys_get_temp_dir(), 'prec_stock_').'.xlsx';
    (new Xlsx($spreadsheet))->save($tmp);

    return new UploadedFile($tmp, 'stock.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

function invokeStockSync(object $service, int $productId, int $warehouseId): void
{
    $method = new ReflectionMethod($service, 'syncCurrentStockFromValuationLayers');
    $method->setAccessible(true);
    $method->invoke($service, $productId, $warehouseId);
}

function createEditedGrnDriftStock(object $testCase, float $quantity = 10.0, float $unitCost = 100.0, float $originalReceiptTotal = 1200.0): void
{
    $stockBatch = StockBatch::factory()->create([
        'product_id' => $testCase->product->id,
        'supplier_id' => $testCase->supplier->id,
        'unit_cost' => $unitCost,
        'selling_price' => 125.0,
    ]);

    $grn = GoodsReceiptNote::factory()->create([
        'supplier_id' => $testCase->supplier->id,
        'warehouse_id' => $testCase->warehouse->id,
        'receipt_date' => now()->toDateString(),
        'status' => 'posted',
        'received_by' => $testCase->user->id,
        'total_quantity' => $quantity,
        'total_amount' => $originalReceiptTotal,
        'grand_total' => $originalReceiptTotal,
    ]);

    $grnItem = GoodsReceiptNoteItem::create([
        'grn_id' => $grn->id,
        'line_no' => 1,
        'product_id' => $testCase->product->id,
        'stock_uom_id' => 24,
        'purchase_uom_id' => 24,
        'qty_in_purchase_uom' => $quantity,
        'uom_conversion_factor' => 1,
        'qty_in_stock_uom' => $quantity,
        'unit_price_per_case' => $unitCost,
        'quantity_ordered' => $quantity,
        'quantity_received' => $quantity,
        'quantity_accepted' => $quantity,
        'quantity_rejected' => 0,
        'unit_cost' => $unitCost,
        'selling_price' => 125.0,
        'total_cost' => $originalReceiptTotal,
        'quality_status' => 'approved',
        'priority_order' => 99,
    ]);

    $stockMovement = StockMovement::create([
        'movement_type' => 'grn',
        'reference_type' => GoodsReceiptNote::class,
        'reference_id' => $grn->id,
        'movement_date' => now()->toDateString(),
        'product_id' => $testCase->product->id,
        'stock_batch_id' => $stockBatch->id,
        'warehouse_id' => $testCase->warehouse->id,
        'quantity' => $quantity,
        'uom_id' => 24,
        'unit_cost' => $unitCost,
        'total_value' => $originalReceiptTotal,
        'created_by' => $testCase->user->id,
    ]);

    StockValuationLayer::create([
        'product_id' => $testCase->product->id,
        'warehouse_id' => $testCase->warehouse->id,
        'stock_batch_id' => $stockBatch->id,
        'stock_movement_id' => $stockMovement->id,
        'grn_item_id' => $grnItem->id,
        'receipt_date' => now()->toDateString(),
        'quantity_received' => $quantity,
        'quantity_remaining' => $quantity,
        'unit_cost' => $unitCost,
        'total_value' => $originalReceiptTotal,
        'value_remaining' => $originalReceiptTotal,
        'priority_order' => 99,
        'is_promotional' => false,
        'is_depleted' => false,
    ]);

    CurrentStockByBatch::create([
        'product_id' => $testCase->product->id,
        'warehouse_id' => $testCase->warehouse->id,
        'stock_batch_id' => $stockBatch->id,
        'quantity_on_hand' => $quantity,
        'unit_cost' => $unitCost,
        'selling_price' => 125.0,
        'total_value' => round($quantity * $unitCost, 4),
        'is_promotional' => false,
        'priority_order' => 99,
        'status' => 'active',
        'last_updated' => now(),
    ]);

    DB::table('current_stock')->insert([
        'product_id' => $testCase->product->id,
        'warehouse_id' => $testCase->warehouse->id,
        'quantity_on_hand' => $quantity,
        'quantity_reserved' => 0,
        'quantity_available' => $quantity,
        'average_cost' => $originalReceiptTotal / $quantity,
        'total_value' => $originalReceiptTotal,
        'total_batches' => 1,
        'promotional_batches' => 0,
        'priority_batches' => 0,
        'last_updated' => now(),
    ]);
}

it('stores invoicePrice rounded to 2dp so total_cost matches unit_cost * qty without float drift', function () {
    $file = makeOpeningStockFile([
        ['SKU' => 'SKU-PREC-001', 'Invoice Price' => 186.27, 'Retail Price' => 200.00, 'Total Inventory in Pieces' => 90247],
    ]);

    $this->post(route('opening-stock.store'), [
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'receipt_date' => now()->toDateString(),
        'import_file' => $file,
    ]);

    $grn = GoodsReceiptNote::where('is_opening_stock', true)->first();
    $item = $grn->items->first();

    expect((float) $item->unit_cost)->toBe(186.27);
    expect((float) $item->total_cost)->toBe(round(186.27 * 90247, 2));
});

it('csb total_value equals GRN item total_cost exactly after posting opening stock', function () {
    $price = 186.27;
    $qty = 90247;
    $expectedTotal = round($price * $qty, 2);

    $file = makeOpeningStockFile([
        ['SKU' => 'SKU-PREC-001', 'Invoice Price' => $price, 'Retail Price' => 200.00, 'Total Inventory in Pieces' => $qty],
    ]);

    $this->post(route('opening-stock.store'), [
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'receipt_date' => now()->toDateString(),
        'import_file' => $file,
    ]);

    $grn = GoodsReceiptNote::where('is_opening_stock', true)->first();
    app(InventoryService::class)->postGrnToInventory($grn);

    $csb = CurrentStockByBatch::where('product_id', $this->product->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();

    expect($csb)->not->toBeNull();
    expect((float) $csb->total_value)->toBe($expectedTotal);
});

it('csb total_value matches the GL debit amount after posting opening stock', function () {
    $price = 186.27;
    $qty = 90247;

    $file = makeOpeningStockFile([
        ['SKU' => 'SKU-PREC-001', 'Invoice Price' => $price, 'Retail Price' => 200.00, 'Total Inventory in Pieces' => $qty],
    ]);

    $this->post(route('opening-stock.store'), [
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'receipt_date' => now()->toDateString(),
        'import_file' => $file,
    ]);

    $grn = GoodsReceiptNote::where('is_opening_stock', true)->first();
    app(InventoryService::class)->postGrnToInventory($grn);

    $csb = CurrentStockByBatch::where('product_id', $this->product->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();

    $glDebit = JournalEntryDetail::whereHas('journalEntry', fn ($q) => $q->where('status', 'posted'))
        ->where('chart_of_account_id', $this->inventoryAccount->id)
        ->sum('debit');

    expect((float) $csb->total_value)->toBe((float) $glDebit);
});

it('valuation layer total_value equals GRN item total_cost after posting', function () {
    $price = 186.27;
    $qty = 90247;
    $expectedTotal = round($price * $qty, 2);

    $file = makeOpeningStockFile([
        ['SKU' => 'SKU-PREC-001', 'Invoice Price' => $price, 'Retail Price' => 200.00, 'Total Inventory in Pieces' => $qty],
    ]);

    $this->post(route('opening-stock.store'), [
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'receipt_date' => now()->toDateString(),
        'import_file' => $file,
    ]);

    $grn = GoodsReceiptNote::where('is_opening_stock', true)->first();
    app(InventoryService::class)->postGrnToInventory($grn);

    $layer = StockValuationLayer::where('product_id', $this->product->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();

    expect($layer)->not->toBeNull();
    expect((float) $layer->total_value)->toBe($expectedTotal);
});

it('resync command reports zero drift on freshly posted data', function () {
    $file = makeOpeningStockFile([
        ['SKU' => 'SKU-PREC-001', 'Invoice Price' => 186.27, 'Retail Price' => 200.00, 'Total Inventory in Pieces' => 90247],
    ]);

    $this->post(route('opening-stock.store'), [
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'receipt_date' => now()->toDateString(),
        'import_file' => $file,
    ]);

    $grn = GoodsReceiptNote::where('is_opening_stock', true)->first();
    app(InventoryService::class)->postGrnToInventory($grn);

    $this->artisan('stock:resync-values', ['--dry-run' => true])
        ->expectsOutputToContain('[Phase A] CSB records updated (or would update) | 0')
        ->expectsOutputToContain('SVL records updated (or would update) | 0')
        ->expectsOutputToContain('current_stock records updated (or would update) | 0')
        ->assertSuccessful();
});

it('current_stock total_value matches remaining valuation layer value after posting opening stock', function () {
    $price = 186.27;
    $qty = 90247;
    $expectedTotal = round($price * $qty, 2);

    $file = makeOpeningStockFile([
        ['SKU' => 'SKU-PREC-001', 'Invoice Price' => $price, 'Retail Price' => 200.00, 'Total Inventory in Pieces' => $qty],
    ]);

    $this->post(route('opening-stock.store'), [
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'receipt_date' => now()->toDateString(),
        'import_file' => $file,
    ]);

    $grn = GoodsReceiptNote::where('is_opening_stock', true)->first();
    app(InventoryService::class)->postGrnToInventory($grn);

    $svlRemainingValue = StockValuationLayer::where('product_id', $this->product->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->where('quantity_remaining', '>', 0)
        ->selectRaw('COALESCE(SUM(quantity_remaining * unit_cost), 0) as total_value')
        ->value('total_value');

    $currentStock = CurrentStock::where('product_id', $this->product->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();

    expect($currentStock)->not->toBeNull();
    expect((float) $currentStock->total_value)->toBe($expectedTotal);
    expect((float) $currentStock->total_value)->toBe((float) $svlRemainingValue);
});

it('distribution stock sync uses remaining value instead of original receipt total after GRN correction', function () {
    createEditedGrnDriftStock($this);

    invokeStockSync(app(DistributionService::class), $this->product->id, $this->warehouse->id);

    $currentStock = CurrentStock::where('product_id', $this->product->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();

    expect((float) $currentStock->quantity_on_hand)->toBe(10.0);
    expect((float) $currentStock->average_cost)->toBe(100.0);
    expect((float) $currentStock->total_value)->toBe(1000.0);
});

it('sales settlement revert stock sync uses remaining value instead of original receipt total after GRN correction', function () {
    createEditedGrnDriftStock($this);

    invokeStockSync(app(SalesSettlementRevertService::class), $this->product->id, $this->warehouse->id);

    $currentStock = CurrentStock::where('product_id', $this->product->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();

    expect((float) $currentStock->quantity_on_hand)->toBe(10.0);
    expect((float) $currentStock->average_cost)->toBe(100.0);
    expect((float) $currentStock->total_value)->toBe(1000.0);
});
