<?php

use App\Models\ChartOfAccount;
use App\Models\GoodsReceiptNote;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'opening-stock-create', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'goods-receipt-note-create', 'guard_name' => 'web']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('opening-stock-create', 'goods-receipt-note-create');
    $this->actingAs($this->user);

    $currency = \App\Models\Currency::create([
        'currency_code' => 'PKR',
        'currency_name' => 'Pakistani Rupee',
        'currency_symbol' => 'Rs',
        'is_base_currency' => true,
    ]);

    \App\Models\AccountingPeriod::create([
        'name' => now()->format('F Y'),
        'start_date' => now()->startOfMonth(),
        'end_date' => now()->endOfMonth(),
        'status' => 'open',
    ]);

    \App\Models\CostCenter::create([
        'code' => 'CC006',
        'name' => 'Warehouse & Inventory',
        'is_active' => true,
    ]);

    $assetType = \App\Models\AccountType::create([
        'type_name' => 'Assets',
        'report_group' => 'BalanceSheet',
        'category' => 'Asset',
    ]);

    $equityType = \App\Models\AccountType::create([
        'type_name' => 'Equity',
        'report_group' => 'BalanceSheet',
        'category' => 'Equity',
    ]);

    $this->inventoryAccount = ChartOfAccount::create([
        'account_type_id' => $assetType->id,
        'currency_id' => $currency->id,
        'account_code' => '1151',
        'account_name' => 'Stock In Hand',
        'normal_balance' => 'debit',
        'is_active' => true,
    ]);

    $this->openingBalanceEquityAccount = ChartOfAccount::create([
        'account_type_id' => $equityType->id,
        'currency_id' => $currency->id,
        'account_code' => '3300',
        'account_name' => 'Opening Balance Equity',
        'normal_balance' => 'credit',
        'is_active' => true,
    ]);

    $this->supplier = Supplier::factory()->create([
        'disabled' => false,
        'sales_tax' => 18.00,
    ]);

    $this->warehouse = Warehouse::factory()->create([
        'id' => 1,
        'disabled' => false,
    ]);

    Uom::factory()->create([
        'id' => 24,
        'uom_name' => 'Piece',
        'symbol' => 'PCS',
        'enabled' => true,
    ]);

    $this->product1 = Product::factory()->create([
        'product_code' => 'SKU-001',
        'product_name' => 'Test Product One',
        'supplier_id' => $this->supplier->id,
        'uom_conversion_factor' => 24,
        'unit_sell_price' => 75.00,
        'is_active' => true,
        'is_powder' => false,
    ]);

    $this->product2 = Product::factory()->create([
        'product_code' => 'SKU-002',
        'product_name' => 'Test Product Two',
        'supplier_id' => $this->supplier->id,
        'uom_conversion_factor' => 12,
        'unit_sell_price' => 100.00,
        'is_active' => true,
        'is_powder' => true,
    ]);
});

function createOpeningStockExcel(array $rows): UploadedFile
{
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();

    $headers = ['SKU', 'Invoice Price', 'Retail Price', 'Total Inventory in Pieces'];

    foreach ($headers as $colIndex => $header) {
        $sheet->setCellValue([$colIndex + 1, 1], $header);
    }

    foreach ($rows as $rowIndex => $row) {
        foreach ($headers as $colIndex => $header) {
            $value = $row[$header] ?? '';
            $sheet->setCellValue([$colIndex + 1, $rowIndex + 2], $value);
        }
    }

    $tempPath = tempnam(sys_get_temp_dir(), 'opening_stock_test_').'.xlsx';
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save($tempPath);

    return new UploadedFile($tempPath, 'opening_stock.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

it('shows the opening stock modal on GRN create page', function () {
    $response = $this->get(route('goods-receipt-notes.create'));

    $response->assertOk();
    $response->assertSee('Opening Stock');
    $response->assertSee($this->supplier->supplier_name);
    $response->assertSee($this->warehouse->warehouse_name);
});

it('downloads template with pre-filled SKUs for supplier', function () {
    $response = $this->get(route('opening-stock.template', $this->supplier));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('imports valid excel and creates draft GRN with correct inventory', function () {
    $file = createOpeningStockExcel([
        ['SKU' => 'SKU-001', 'Invoice Price' => 50.00, 'Retail Price' => 75.00, 'Total Inventory in Pieces' => 100],
        ['SKU' => 'SKU-002', 'Invoice Price' => 80.00, 'Retail Price' => 120.00, 'Total Inventory in Pieces' => 200],
    ]);

    $response = $this->post(route('opening-stock.store'), [
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'receipt_date' => '2026-03-15',
        'import_file' => $file,
    ]);

    $grn = GoodsReceiptNote::where('is_opening_stock', true)->first();
    $response->assertRedirect(route('goods-receipt-notes.show', $grn));
    $response->assertSessionHas('success');

    expect($grn)->not->toBeNull();
    expect($grn->status)->toBe('draft');
    expect($grn->is_opening_stock)->toBeTrue();
    expect($grn->warehouse_id)->toBe($this->warehouse->id);
    expect((float) $grn->total_quantity)->toBe(300.0);
    expect((float) $grn->grand_total)->toBe(21000.0);

    expect($grn->items)->toHaveCount(2);

    $item1 = $grn->items->where('product_id', $this->product1->id)->first();
    expect((float) $item1->unit_cost)->toBe(50.0);
    expect((float) $item1->selling_price)->toBe(75.0);
    expect((float) $item1->quantity_accepted)->toBe(100.0);

    $item2 = $grn->items->where('product_id', $this->product2->id)->first();
    expect((float) $item2->unit_cost)->toBe(80.0);
    expect((float) $item2->selling_price)->toBe(120.0);
    expect((float) $item2->quantity_accepted)->toBe(200.0);
});

it('does not create journal entry or post inventory for draft GRN', function () {
    $file = createOpeningStockExcel([
        ['SKU' => 'SKU-001', 'Invoice Price' => 50.00, 'Retail Price' => 75.00, 'Total Inventory in Pieces' => 100],
    ]);

    $this->post(route('opening-stock.store'), [
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'receipt_date' => '2026-03-15',
        'import_file' => $file,
    ]);

    $grn = GoodsReceiptNote::where('is_opening_stock', true)->first();
    expect($grn->status)->toBe('draft');
    expect($grn->journal_entry_id)->toBeNull();
    expect(JournalEntry::count())->toBe(0);
});

it('does not create supplier payment for opening stock', function () {
    $file = createOpeningStockExcel([
        ['SKU' => 'SKU-001', 'Invoice Price' => 50.00, 'Retail Price' => 75.00, 'Total Inventory in Pieces' => 100],
    ]);

    $this->post(route('opening-stock.store'), [
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'receipt_date' => '2026-03-15',
        'import_file' => $file,
    ]);

    expect(SupplierPayment::count())->toBe(0);
});

it('validates required fields', function () {
    $this->post(route('opening-stock.store'), [])
        ->assertSessionHasErrors(['supplier_id', 'warehouse_id', 'receipt_date', 'import_file']);
});

it('rejects invalid file type', function () {
    $file = UploadedFile::fake()->create('test.pdf', 100);

    $this->post(route('opening-stock.store'), [
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'receipt_date' => '2026-03-15',
        'import_file' => $file,
    ])->assertSessionHasErrors(['import_file']);
});

it('shows row errors for invalid SKU', function () {
    $file = createOpeningStockExcel([
        ['SKU' => 'INVALID-SKU', 'Invoice Price' => 50.00, 'Retail Price' => 75.00, 'Total Inventory in Pieces' => 100],
    ]);

    $this->post(route('opening-stock.store'), [
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'receipt_date' => '2026-03-15',
        'import_file' => $file,
    ])->assertSessionHasErrors(['opening_stock_file']);
});

it('shows row errors for zero quantity', function () {
    $file = createOpeningStockExcel([
        ['SKU' => 'SKU-001', 'Invoice Price' => 50.00, 'Retail Price' => 75.00, 'Total Inventory in Pieces' => 0],
    ]);

    $this->post(route('opening-stock.store'), [
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'receipt_date' => '2026-03-15',
        'import_file' => $file,
    ])->assertSessionHasErrors(['opening_stock_file']);
});

it('rejects unauthenticated users', function () {
    auth()->logout();

    $this->post(route('opening-stock.store'))
        ->assertRedirect(route('login'));
});

it('rejects users without permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post(route('opening-stock.store'))
        ->assertForbidden();
});
