<?php

use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteItem;
use App\Models\Product;
use App\Models\PromotionalCampaign;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach ([
        'goods-receipt-note-list',
        'goods-receipt-note-create',
        'goods-receipt-note-edit',
        'goods-receipt-note-delete',
        'goods-receipt-note-post',
        'goods-receipt-note-reverse',
        'goods-receipt-note-import',
    ] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('goods-receipt-note-import', 'goods-receipt-note-create', 'goods-receipt-note-edit', 'goods-receipt-note-list');
    $this->actingAs($this->user);

    $this->supplier = Supplier::factory()->create([
        'disabled' => false,
        'sales_tax' => 18.00,
    ]);

    $this->warehouse = Warehouse::factory()->create(['disabled' => false]);

    $this->caseUom = Uom::factory()->create([
        'id' => 33,
        'uom_name' => 'Case',
        'symbol' => 'CS',
        'enabled' => true,
    ]);

    $this->pieceUom = Uom::factory()->create([
        'id' => 24,
        'uom_name' => 'Piece',
        'symbol' => 'PCS',
        'enabled' => true,
    ]);

    $this->product1 = Product::factory()->create([
        'product_code' => 'TEST-001',
        'product_name' => 'Test Product One',
        'supplier_id' => $this->supplier->id,
        'uom_conversion_factor' => 24,
        'unit_sell_price' => 75.00,
        'is_active' => true,
        'is_powder' => false,
    ]);

    $this->product2 = Product::factory()->create([
        'product_code' => 'TEST-002',
        'product_name' => 'Test Product Two',
        'supplier_id' => $this->supplier->id,
        'uom_conversion_factor' => 12,
        'unit_sell_price' => 100.00,
        'is_active' => true,
        'is_powder' => true,
    ]);
});

function createTestExcelFile(array $rows): UploadedFile
{
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();

    $headers = [
        'Product Code',
        'Quantity',
        'Unit Price Per Case',
        'Discount Value',
        'FMR Allowance',
        'Excise Duty',
        'Sales Tax Value',
        'Advance Income Tax',
        'Selling Price',
        'Promotional Price',
        'Priority Order',
        'Batch Number',
        'Must Sell Before',
        'Manufacturing Date',
        'Expiry Date',
    ];

    foreach ($headers as $colIndex => $header) {
        $sheet->setCellValue([$colIndex + 1, 1], $header);
    }

    foreach ($rows as $rowIndex => $row) {
        foreach ($headers as $colIndex => $header) {
            $value = $row[$header] ?? '';
            $sheet->setCellValue([$colIndex + 1, $rowIndex + 2], $value);
        }
    }

    $tempPath = tempnam(sys_get_temp_dir(), 'grn_test_').'.xlsx';
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save($tempPath);

    return new UploadedFile($tempPath, 'test_import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

it('imports valid excel and creates draft GRN with correct items', function () {
    $file = createTestExcelFile([
        [
            'Product Code' => 'TEST-001',
            'Quantity' => 10,
            'Unit Price Per Case' => 1500.00,
            'Discount Value' => 200,
            'FMR Allowance' => 100,
            'Excise Duty' => 50,
            'Sales Tax Value' => '',
            'Advance Income Tax' => 25,
            'Selling Price' => '',
            'Promotional Price' => '',
            'Priority Order' => '',
            'Batch Number' => 'BATCH-A1',
            'Must Sell Before' => '',
            'Manufacturing Date' => '',
            'Expiry Date' => '',
        ],
        [
            'Product Code' => 'TEST-002',
            'Quantity' => 5,
            'Unit Price Per Case' => 2000.00,
            'Discount Value' => 0,
            'FMR Allowance' => 0,
            'Excise Duty' => 0,
            'Sales Tax Value' => '',
            'Advance Income Tax' => 0,
            'Selling Price' => 90,
            'Promotional Price' => '',
            'Priority Order' => '',
            'Batch Number' => '',
            'Must Sell Before' => '',
            'Manufacturing Date' => '',
            'Expiry Date' => '',
        ],
    ]);

    $response = $this->post(route('goods-receipt-notes.import'), [
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'receipt_date' => '2026-02-17',
        'supplier_invoice_number' => 'INV-TEST-001',
        'supplier_invoice_date' => '2026-02-17',
        'import_file' => $file,
    ]);

    $grn = GoodsReceiptNote::latest()->first();
    expect($grn)->not->toBeNull();
    expect($grn->status)->toBe('draft');
    expect($grn->supplier_id)->toBe($this->supplier->id);
    expect($grn->warehouse_id)->toBe($this->warehouse->id);
    expect($grn->supplier_invoice_number)->toBe('INV-TEST-001');
    expect($grn->items)->toHaveCount(2);

    $response->assertRedirect(route('goods-receipt-notes.edit', $grn));
});

it('fetches uom_conversion_factor from product not from excel', function () {
    $file = createTestExcelFile([
        [
            'Product Code' => 'TEST-001',
            'Quantity' => 10,
            'Unit Price Per Case' => 1000.00,
        ],
    ]);

    $this->post(route('goods-receipt-notes.import'), [
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'receipt_date' => '2026-02-17',
        'import_file' => $file,
    ]);

    $item = GoodsReceiptNoteItem::latest()->first();
    expect((float) $item->uom_conversion_factor)->toBe(24.0000);
    expect((float) $item->qty_in_stock_uom)->toBe(240.00);
});

it('defaults purchase_uom_id to 33 (Case)', function () {
    $file = createTestExcelFile([
        [
            'Product Code' => 'TEST-001',
            'Quantity' => 5,
            'Unit Price Per Case' => 500.00,
        ],
    ]);

    $this->post(route('goods-receipt-notes.import'), [
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'receipt_date' => '2026-02-17',
        'import_file' => $file,
    ]);

    $item = GoodsReceiptNoteItem::latest()->first();
    expect($item->purchase_uom_id)->toBe(33);
    expect($item->stock_uom_id)->toBe(24);
});

it('calculates extended_value correctly', function () {
    $file = createTestExcelFile([
        [
            'Product Code' => 'TEST-001',
            'Quantity' => 10,
            'Unit Price Per Case' => 1500.00,
        ],
    ]);

    $this->post(route('goods-receipt-notes.import'), [
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'receipt_date' => '2026-02-17',
        'import_file' => $file,
    ]);

    $item = GoodsReceiptNoteItem::latest()->first();
    expect((float) $item->extended_value)->toBe(15000.00);
});

it('calculates discounted_value_before_tax correctly', function () {
    $file = createTestExcelFile([
        [
            'Product Code' => 'TEST-001',
            'Quantity' => 10,
            'Unit Price Per Case' => 1500.00,
            'Discount Value' => 200,
            'FMR Allowance' => 100,
        ],
    ]);

    $this->post(route('goods-receipt-notes.import'), [
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'receipt_date' => '2026-02-17',
        'import_file' => $file,
    ]);

    $item = GoodsReceiptNoteItem::latest()->first();
    expect((float) $item->discounted_value_before_tax)->toBe(14700.00);
});

it('auto calculates sales tax from supplier rate when blank', function () {
    $file = createTestExcelFile([
        [
            'Product Code' => 'TEST-001',
            'Quantity' => 10,
            'Unit Price Per Case' => 1500.00,
            'Discount Value' => 200,
            'FMR Allowance' => 100,
            'Sales Tax Value' => '',
        ],
    ]);

    $this->post(route('goods-receipt-notes.import'), [
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'receipt_date' => '2026-02-17',
        'import_file' => $file,
    ]);

    $item = GoodsReceiptNoteItem::latest()->first();
    expect((float) $item->sales_tax_value)->toBe(2646.00);
});

it('uses provided sales tax value when manually specified', function () {
    $file = createTestExcelFile([
        [
            'Product Code' => 'TEST-001',
            'Quantity' => 10,
            'Unit Price Per Case' => 1500.00,
            'Discount Value' => 200,
            'FMR Allowance' => 100,
            'Sales Tax Value' => 999.99,
        ],
    ]);

    $this->post(route('goods-receipt-notes.import'), [
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'receipt_date' => '2026-02-17',
        'import_file' => $file,
    ]);

    $item = GoodsReceiptNoteItem::latest()->first();
    expect((float) $item->sales_tax_value)->toBe(999.99);
});

it('calculates unit_cost including fmr_allowance correctly', function () {
    $file = createTestExcelFile([
        [
            'Product Code' => 'TEST-001',
            'Quantity' => 10,
            'Unit Price Per Case' => 1500.00,
            'Discount Value' => 200,
            'FMR Allowance' => 100,
            'Excise Duty' => 50,
            'Sales Tax Value' => 2646,
            'Advance Income Tax' => 25,
        ],
    ]);

    $this->post(route('goods-receipt-notes.import'), [
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'receipt_date' => '2026-02-17',
        'import_file' => $file,
    ]);

    $item = GoodsReceiptNoteItem::latest()->first();
    expect((float) $item->total_value_with_taxes)->toBe(17421.00);
    expect(round((float) $item->unit_cost, 2))->toBe(73.00);
});

it('creates promotional campaign when promotional_price is provided', function () {
    $file = createTestExcelFile([
        [
            'Product Code' => 'TEST-001',
            'Quantity' => 10,
            'Unit Price Per Case' => 1500.00,
            'Promotional Price' => 65.00,
        ],
    ]);

    $this->post(route('goods-receipt-notes.import'), [
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'receipt_date' => '2026-02-17',
        'import_file' => $file,
    ]);

    $item = GoodsReceiptNoteItem::latest()->first();
    expect($item->is_promotional)->toBeTrue();
    expect((float) $item->promotional_price)->toBe(65.00);
    expect((float) $item->selling_price)->toBe(65.00);
    expect($item->promotional_campaign_id)->not->toBeNull();

    $campaign = PromotionalCampaign::find($item->promotional_campaign_id);
    expect($campaign)->not->toBeNull();
    expect((float) $campaign->discount_value)->toBe(65.00);
    expect($campaign->discount_type)->toBe('special_price');
});

it('overrides selling_price with promotional_price when promotional_price is set', function () {
    $file = createTestExcelFile([
        [
            'Product Code' => 'TEST-001',
            'Quantity' => 5,
            'Unit Price Per Case' => 1000.00,
            'Selling Price' => 120.00,
            'Promotional Price' => 55.00,
        ],
    ]);

    $this->post(route('goods-receipt-notes.import'), [
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'receipt_date' => '2026-02-17',
        'import_file' => $file,
    ]);

    $item = GoodsReceiptNoteItem::latest()->first();
    expect((float) $item->selling_price)->toBe(55.00);
    expect((float) $item->promotional_price)->toBe(55.00);
    expect($item->is_promotional)->toBeTrue();
});

it('stores GRN header fields from modal correctly', function () {
    $file = createTestExcelFile([
        [
            'Product Code' => 'TEST-001',
            'Quantity' => 5,
            'Unit Price Per Case' => 1000.00,
        ],
    ]);

    $this->post(route('goods-receipt-notes.import'), [
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'receipt_date' => '2026-01-15',
        'supplier_invoice_number' => 'SI-2026-100',
        'supplier_invoice_date' => '2026-01-14',
        'import_file' => $file,
    ]);

    $grn = GoodsReceiptNote::latest()->first();
    expect($grn->supplier_id)->toBe($this->supplier->id);
    expect($grn->warehouse_id)->toBe($this->warehouse->id);
    expect($grn->receipt_date->format('Y-m-d'))->toBe('2026-01-15');
    expect($grn->supplier_invoice_number)->toBe('SI-2026-100');
    expect($grn->supplier_invoice_date->format('Y-m-d'))->toBe('2026-01-14');
});

it('returns error for invalid product code with row number', function () {
    $file = createTestExcelFile([
        [
            'Product Code' => 'INVALID-999',
            'Quantity' => 10,
            'Unit Price Per Case' => 1500.00,
        ],
    ]);

    $response = $this->post(route('goods-receipt-notes.import'), [
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'receipt_date' => '2026-02-17',
        'import_file' => $file,
    ]);

    $response->assertSessionHasErrors('import_file');
    expect(GoodsReceiptNote::count())->toBe(0);
});

it('returns error for product not belonging to supplier', function () {
    $otherSupplier = Supplier::factory()->create(['disabled' => false]);
    Product::factory()->create([
        'product_code' => 'OTHER-001',
        'supplier_id' => $otherSupplier->id,
        'is_active' => true,
    ]);

    $file = createTestExcelFile([
        [
            'Product Code' => 'OTHER-001',
            'Quantity' => 10,
            'Unit Price Per Case' => 1500.00,
        ],
    ]);

    $response = $this->post(route('goods-receipt-notes.import'), [
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'receipt_date' => '2026-02-17',
        'import_file' => $file,
    ]);

    $response->assertSessionHasErrors('import_file');
    expect(GoodsReceiptNote::count())->toBe(0);
});

it('denies import without goods-receipt-note-import permission', function () {
    $userWithout = User::factory()->create();
    $userWithout->givePermissionTo('goods-receipt-note-create');
    $this->actingAs($userWithout);

    $file = createTestExcelFile([
        [
            'Product Code' => 'TEST-001',
            'Quantity' => 10,
            'Unit Price Per Case' => 1500.00,
        ],
    ]);

    $response = $this->post(route('goods-receipt-notes.import'), [
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'receipt_date' => '2026-02-17',
        'import_file' => $file,
    ]);

    $response->assertForbidden();
});

it('allows import with goods-receipt-note-import permission', function () {
    $file = createTestExcelFile([
        [
            'Product Code' => 'TEST-001',
            'Quantity' => 5,
            'Unit Price Per Case' => 1000.00,
        ],
    ]);

    $response = $this->post(route('goods-receipt-notes.import'), [
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'receipt_date' => '2026-02-17',
        'import_file' => $file,
    ]);

    $response->assertRedirect();
    expect(GoodsReceiptNote::count())->toBe(1);
});

it('downloads import template as excel file', function () {
    $response = $this->get(route('goods-receipt-notes.import-template'));

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('denies template download without import permission', function () {
    $userWithout = User::factory()->create();
    $userWithout->givePermissionTo('goods-receipt-note-list');
    $this->actingAs($userWithout);

    $response = $this->get(route('goods-receipt-notes.import-template'));

    $response->assertForbidden();
});

it('rolls back transaction when row has errors', function () {
    $file = createTestExcelFile([
        [
            'Product Code' => 'TEST-001',
            'Quantity' => 10,
            'Unit Price Per Case' => 1500.00,
        ],
        [
            'Product Code' => 'INVALID-PRODUCT',
            'Quantity' => 5,
            'Unit Price Per Case' => 2000.00,
        ],
    ]);

    $this->post(route('goods-receipt-notes.import'), [
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'receipt_date' => '2026-02-17',
        'import_file' => $file,
    ]);

    expect(GoodsReceiptNote::count())->toBe(0);
    expect(GoodsReceiptNoteItem::count())->toBe(0);
});

it('validates required fields in import form', function () {
    $response = $this->post(route('goods-receipt-notes.import'), []);

    $response->assertSessionHasErrors(['supplier_id', 'warehouse_id', 'receipt_date', 'import_file']);
});

it('sets selling_price from product when not in excel', function () {
    $file = createTestExcelFile([
        [
            'Product Code' => 'TEST-001',
            'Quantity' => 5,
            'Unit Price Per Case' => 1000.00,
            'Selling Price' => '',
        ],
    ]);

    $this->post(route('goods-receipt-notes.import'), [
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'receipt_date' => '2026-02-17',
        'import_file' => $file,
    ]);

    $item = GoodsReceiptNoteItem::latest()->first();
    expect((float) $item->selling_price)->toBe(75.00);
});
