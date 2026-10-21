<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['product-list', 'product-create', 'product-edit', 'product-delete'] as $permission) {
        Permission::findOrCreate($permission);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['product-list', 'product-create', 'product-edit', 'product-delete']);
    $this->actingAs($this->user);
});

function productCsv(array $rows): UploadedFile
{
    $header = 'Product Code,Product Name,Category,Supplier,UOM,Sales UOM,Valuation Method,Sell Price,Cost Price,Barcode,Powder?,Status';

    return UploadedFile::fake()->createWithContent('products.csv', implode("\n", [$header, ...array_map(fn ($r) => implode(',', $r), $rows)]));
}

function runProductImport(array $rows, array $options = [])
{
    return test()->post(route('products.import'), $options + ['import_file' => productCsv($rows)]);
}

it('deactivates instead of deleting a product', function () {
    $product = Product::factory()->create(['is_active' => true]);

    $this->delete(route('products.destroy', $product))->assertRedirect(route('products.index'));

    $this->assertDatabaseHas('products', ['id' => $product->id, 'is_active' => false, 'deleted_at' => null]);
});

it('renders the index, create, edit and view pages', function () {
    $product = Product::factory()->create(['product_name' => 'Page Test Oil']);

    $this->get(route('products.index'))->assertSuccessful()->assertSee('Page Test Oil');
    $this->get(route('products.create'))->assertSuccessful()->assertSee('Product details');
    $this->get(route('products.edit', $product))->assertSuccessful()->assertSee('Confirm Update');
    $this->get(route('products.show', $product))->assertSuccessful()->assertSee('Page Test Oil');
});

it('searches products by name, code, brand or barcode', function () {
    Product::factory()->create(['product_name' => 'Zeta Ghee', 'brand' => 'Zed']);
    Product::factory()->create(['product_name' => 'Alpha Oil', 'brand' => 'Alp']);

    $this->get(route('products.index', ['filter' => ['search' => 'Zed']]))
        ->assertSuccessful()
        ->assertSee('Zeta Ghee')
        ->assertDontSee('Alpha Oil');
});

it('shows the import page and downloads the template', function () {
    $this->get(route('products.import.form'))->assertSuccessful()->assertSee('Import products from Excel');
    $this->get(route('products.import.template'))->assertDownload('products_import_template.xlsx');
});

it('imports products and skips existing codes', function () {
    $uom = Uom::factory()->create(['uom_name' => 'Piece']);
    $case = Uom::factory()->create(['uom_name' => 'Case']);
    $supplier = Supplier::factory()->create(['supplier_name' => 'Mezan']);
    $category = Category::create(['name' => 'Cooking Oil']);
    Product::factory()->create(['product_code' => 'OLD-1']);

    runProductImport([
        ['new-1', 'Olivola 1L', 'Cooking Oil', 'Mezan', 'Piece', 'Case', 'fifo', '626', '596', '', 'No', 'Active'],
        ['OLD-1', 'Existing', '', '', 'Piece', '', '', '', '', '', '', ''],
        ['NEW-1', 'Repeat in file', '', '', 'Piece', '', '', '', '', '', '', ''],
    ])->assertSessionHas('import_result.counts', ['created' => 1, 'skipped' => 2, 'errors' => 0]);

    expect(Product::where('product_code', 'NEW-1')->first())
        ->product_name->toBe('Olivola 1L')
        ->uom_id->toBe($uom->id)
        ->sales_uom_id->toBe($case->id)
        ->supplier_id->toBe($supplier->id)
        ->category_id->toBe($category->id)
        ->valuation_method->toBe('FIFO')
        ->and((float) Product::where('product_code', 'NEW-1')->value('unit_sell_price'))->toBe(626.0);
});

it('reports unknown lookups and missing fields without importing those rows', function () {
    Uom::factory()->create(['uom_name' => 'Piece']);

    runProductImport([
        ['BAD-1', 'Bad Category', 'Nope', '', 'Piece', '', '', '', '', '', '', ''],
        ['', 'No Code', '', '', 'Piece', '', '', '', '', '', '', ''],
        ['GOOD-1', 'Good', '', '', 'Piece', '', '', '', '', '', '', ''],
    ])->assertSessionHas('import_result.counts', ['created' => 1, 'skipped' => 0, 'errors' => 2]);

    expect(Product::pluck('product_code')->all())->toBe(['GOOD-1']);
});

it('saves nothing when check only is selected', function () {
    Uom::factory()->create(['uom_name' => 'Piece']);

    runProductImport([['DRY-1', 'Dry', '', '', 'Piece', '', '', '', '', '', '', '']], ['dry_run' => 1])
        ->assertSessionHas('import_result.counts.created', 1);

    expect(Product::count())->toBe(0);
});

it('requires product-create to import', function () {
    $this->user->revokePermissionTo('product-create');

    runProductImport([['X-1', 'X', '', '', 'Piece', '', '', '', '', '', '', '']])->assertForbidden();
});
