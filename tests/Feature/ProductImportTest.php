<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPriceChangeLog;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['product-list', 'product-create', 'product-edit'] as $permission) {
        Permission::findOrCreate($permission);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['product-list', 'product-create', 'product-edit']);
    $this->actingAs($this->user);

    $this->supplier = Supplier::factory()->create(['supplier_name' => 'Pakistan Tobbaco Company']);
    $this->category = Category::create(['name' => 'FMC', 'slug' => 'fmc', 'is_active' => true]);
    $this->piece = Uom::factory()->create(['uom_name' => 'Piece']);
});

function ptcImportCsv(array $rows, string $header = 'Product Code,Product Name,Brand,Category,Supplier,UOM,Sell Price,Cost Price,Expiry Price,Status'): UploadedFile
{
    return UploadedFile::fake()->createWithContent('products.csv', implode("\n", [$header, ...array_map(fn ($row) => implode(',', $row), $rows)]));
}

function runPtcImport(array $rows, array $options = [], ?string $header = null)
{
    return test()->post(route('products.import'), $options + [
        'import_file' => $header ? ptcImportCsv($rows, $header) : ptcImportCsv($rows),
    ]);
}

function existingPtcProduct(array $overrides = []): Product
{
    return Product::factory()->create($overrides + [
        'product_code' => 'DUN-20',
        'product_name' => 'Dunhill Light',
        'brand' => 'DHL',
        'supplier_id' => test()->supplier->id,
        'category_id' => test()->category->id,
        'uom_id' => test()->piece->id,
        'unit_sell_price' => 5500,
        'cost_price' => 5300,
        'expiry_price' => 0,
        'is_active' => true,
    ]);
}

it('skips an existing product code by default and leaves it untouched', function () {
    $product = existingPtcProduct();

    runPtcImport([['DUN-20', 'Dunhill Light', 'DHL', 'FMC', 'Pakistan Tobbaco Company', 'Piece', '5786.06', '5542', '0', 'Active']])
        ->assertSessionHas('import_result.counts.skipped', 1)
        ->assertSessionHas('import_result.counts.updated', 0);

    expect((float) $product->fresh()->unit_sell_price)->toBe(5500.0);
});

it('updates a matching product when asked, changing only the columns that differ', function () {
    $product = existingPtcProduct();

    runPtcImport([
        ['DUN-20', 'Dunhill Light', 'DHL', 'FMC', 'Pakistan Tobbaco Company', 'Piece', '5786.06', '5542', '0', 'Active'],
    ], ['update_existing' => '1'])
        ->assertSessionHas('import_result.counts.updated', 1)
        ->assertSessionHas('import_result.counts.created', 0)
        ->assertSessionHas('import_result.report.0.action', 'updated');

    $fresh = $product->fresh();
    expect((float) $fresh->unit_sell_price)->toBe(5786.06)
        ->and((float) $fresh->cost_price)->toBe(5542.0)
        ->and($fresh->product_name)->toBe('Dunhill Light')
        ->and(ProductPriceChangeLog::where('product_id', $product->id)->pluck('price_type')->sort()->values()->all())->toBe(['cost_price', 'selling_price']);
});

it('check only shows old to new for each change and saves nothing', function () {
    $product = existingPtcProduct();

    $response = runPtcImport([
        ['DUN-20', 'Dunhill Light', 'DHL', 'FMC', 'Pakistan Tobbaco Company', 'Piece', '5786.06', '5300', '0', 'Active'],
        ['NEW-1', 'Brand New', 'BN', 'FMC', 'Pakistan Tobbaco Company', 'Piece', '100', '90', '0', 'Active'],
        ['BAD-1', 'Bad Row', 'BD', 'Nope', 'Pakistan Tobbaco Company', 'Piece', '100', '90', '0', 'Active'],
    ], ['update_existing' => '1', 'dry_run' => '1']);

    $response->assertSessionHas('import_result.dry_run', true)
        ->assertSessionHas('import_result.counts.updated', 1)
        ->assertSessionHas('import_result.counts.created', 1)
        ->assertSessionHas('import_result.counts.errors', 1);

    $report = collect(session('import_result.report'))->keyBy('code');
    expect($report['DUN-20']['message'])->toContain('Sell Price 5,500 → 5,786.06')
        ->and($report['DUN-20']['message'])->not->toContain('Cost Price')
        ->and($report['NEW-1']['action'])->toBe('created')
        ->and($report['BAD-1']['action'])->toBe('error');

    expect((float) $product->fresh()->unit_sell_price)->toBe(5500.0)
        ->and(Product::where('product_code', 'NEW-1')->exists())->toBeFalse()
        ->and(ProductPriceChangeLog::count())->toBe(0);
});

it('keeps stored values for blank cells and columns missing from the file', function () {
    $product = existingPtcProduct(['barcode' => '123456', 'weight' => 2.5, 'is_powder' => true]);

    runPtcImport([
        ['DUN-20', '', '', '', '', '', '5900'],
    ], ['update_existing' => '1'], 'Product Code,Product Name,Brand,Category,Supplier,UOM,Sell Price')
        ->assertSessionHas('import_result.counts.updated', 1);

    $fresh = $product->fresh();
    expect((float) $fresh->unit_sell_price)->toBe(5900.0)
        ->and($fresh->brand)->toBe('DHL')
        ->and($fresh->barcode)->toBe('123456')
        ->and((float) $fresh->weight)->toBe(2.5)
        ->and($fresh->is_powder)->toBeTrue()
        ->and($fresh->is_active)->toBeTrue()
        ->and($fresh->category_id)->toBe($this->category->id)
        ->and($fresh->uom_id)->toBe($this->piece->id);
});

it('updates prices only when asked and ignores the other columns of the file', function () {
    $product = existingPtcProduct();

    runPtcImport([
        ['DUN-20', 'Totally New Name', 'NEWBRAND', 'NoSuchCategory', 'Pakistan Tobbaco Company', 'Piece', '5786.06', '5542', '0', 'Active'],
    ], ['update_existing' => '1', 'update_scope' => 'prices'])
        ->assertSessionHas('import_result.counts.updated', 1)
        ->assertSessionHas('import_result.counts.errors', 0);

    $fresh = $product->fresh();
    expect((float) $fresh->unit_sell_price)->toBe(5786.06)
        ->and((float) $fresh->cost_price)->toBe(5542.0)
        ->and($fresh->product_name)->toBe('Dunhill Light')
        ->and($fresh->brand)->toBe('DHL')
        ->and($fresh->category_id)->toBe($this->category->id);
});

it('reports a matching row with nothing different as already up to date', function () {
    existingPtcProduct();

    runPtcImport([['DUN-20', 'Dunhill Light', 'DHL', 'FMC', 'Pakistan Tobbaco Company', 'Piece', '5500', '5300', '0', 'Active']], ['update_existing' => '1'])
        ->assertSessionHas('import_result.counts.unchanged', 1)
        ->assertSessionHas('import_result.counts.updated', 0);
});

it('applies a changed sell price to the batches in stock', function () {
    $product = existingPtcProduct();
    $batchId = DB::table('stock_batches')->insertGetId([
        'batch_code' => 'B-1', 'product_id' => $product->id, 'supplier_id' => $this->supplier->id, 'receipt_date' => now()->toDateString(),
        'unit_cost' => 5300, 'selling_price' => 5500, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $warehouseId = DB::table('warehouses')->insertGetId(['warehouse_name' => 'W', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('current_stock_by_batch')->insert([
        'product_id' => $product->id, 'warehouse_id' => $warehouseId, 'stock_batch_id' => $batchId, 'quantity_on_hand' => 10,
        'unit_cost' => 5300, 'selling_price' => 5500, 'total_value' => 53000, 'status' => 'active', 'last_updated' => now(),
    ]);

    runPtcImport([['DUN-20', 'Dunhill Light', 'DHL', 'FMC', 'Pakistan Tobbaco Company', 'Piece', '5786.06', '5300', '0', 'Active']], ['update_existing' => '1', 'dry_run' => '1']);
    expect(session('import_result.report.0.message'))->toContain('applies to 1 stock batch');
    expect((float) DB::table('stock_batches')->where('id', $batchId)->value('selling_price'))->toBe(5500.0);

    runPtcImport([['DUN-20', 'Dunhill Light', 'DHL', 'FMC', 'Pakistan Tobbaco Company', 'Piece', '5786.06', '5300', '0', 'Active']], ['update_existing' => '1']);
    expect((float) DB::table('stock_batches')->where('id', $batchId)->value('selling_price'))->toBe(5786.06)
        ->and((float) DB::table('current_stock_by_batch')->where('stock_batch_id', $batchId)->value('selling_price'))->toBe(5786.06);
});

it('never touches deleted products or lets a barcode move to another product', function () {
    $deleted = existingPtcProduct(['product_code' => 'DEL-1']);
    $deleted->delete();
    existingPtcProduct(['product_code' => 'OTHER', 'barcode' => 'BC-1']);
    $target = existingPtcProduct(['product_code' => 'TARGET']);

    runPtcImport([
        ['DEL-1', 'Dunhill Light', 'DHL', 'FMC', 'Pakistan Tobbaco Company', 'Piece', '9999', '5300', '0', 'Active'],
        ['TARGET', 'Dunhill Light', 'DHL', 'FMC', 'Pakistan Tobbaco Company', 'Piece', '9999', '5300', '0', 'Active'],
    ], ['update_existing' => '1'], 'Product Code,Product Name,Brand,Category,Supplier,UOM,Sell Price,Cost Price,Expiry Price,Status');

    $report = collect(session('import_result.report'))->keyBy('code');
    expect($report['DEL-1']['action'])->toBe('skipped')
        ->and((float) Product::withTrashed()->where('product_code', 'DEL-1')->value('unit_sell_price'))->toBe(5500.0)
        ->and((float) $target->fresh()->unit_sell_price)->toBe(9999.0);

    runPtcImport([['TARGET', '', '', '', '', '', '1', '', '', '', 'BC-1']], ['update_existing' => '1'], 'Product Code,Product Name,Brand,Category,Supplier,UOM,Sell Price,Cost Price,Expiry Price,Status,Barcode')
        ->assertSessionHas('import_result.counts.errors', 1);
});

it('ignores the update option for a user who cannot edit products', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['product-list', 'product-create']);
    $this->actingAs($viewer);
    $product = existingPtcProduct();

    runPtcImport([['DUN-20', 'Dunhill Light', 'DHL', 'FMC', 'Pakistan Tobbaco Company', 'Piece', '1', '5300', '0', 'Active']], ['update_existing' => '1'])
        ->assertSessionHas('import_result.counts.skipped', 1);

    expect((float) $product->fresh()->unit_sell_price)->toBe(5500.0);
    $this->get(route('products.import.form'))->assertDontSee('name="update_existing"', false);
});

it('shows the update option on the import screen to users who can edit', function () {
    $this->get(route('products.import.form'))
        ->assertSuccessful()
        ->assertSee('Check only — show what would happen without saving')
        ->assertSee('name="update_existing"', false)
        ->assertSee('Flow: how an import runs');
});

it('recognises the misspelt Conversion Fector heading and reports columns it cannot use', function () {
    $product = existingPtcProduct(['uom_conversion_factor' => 1]);

    runPtcImport([['DUN-20', '5', 'Red']], ['update_existing' => '1', 'dry_run' => '1'], 'Product Code,Conversion Fector,Colour')
        ->assertSessionHas('import_result.counts.updated', 1)
        ->assertSessionHas('import_result.ignored_columns', ['colour']);

    expect(session('import_result.report.0.message'))->toBe('Conversion Factor 1 → 5');

    runPtcImport([['DUN-20', '5']], ['update_existing' => '1'], 'Product Code,Conversion Fector');
    expect((float) $product->fresh()->uom_conversion_factor)->toBe(5.0);
});
