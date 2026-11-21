<?php

use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Models\Product;
use App\Models\ProductPriceChangeLog;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\Ticket;
use App\Models\Uom;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'report-audit-product-price-change-log', 'guard_name' => 'web']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('report-audit-product-price-change-log');
    $this->actingAs($this->user);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function priceLog(Product $product, array $attributes = []): ProductPriceChangeLog
{
    return ProductPriceChangeLog::create(array_merge([
        'product_id' => $product->id,
        'changed_by' => test()->user->id,
        'price_type' => 'selling_price',
        'old_price' => 100,
        'new_price' => 110,
        'impacted_batch_count' => 0,
        'changed_at' => now(),
    ], $attributes));
}

/**
 * The product names of the rows the report lists.
 *
 * @param  array<string, mixed>  $filter
 * @return array<int, string>
 */
function listedProducts(array $filter): array
{
    return test()->get(route('reports.product-price-change-log.index', ['filter' => $filter]))
        ->assertSuccessful()
        ->viewData('logs')
        ->getCollection()
        ->map(fn (ProductPriceChangeLog $log) => $log->product->product_name)
        ->sort()->values()->all();
}

function makeAdmin(User $user): void
{
    $user->forceFill(['is_super_admin' => 'Yes'])->save();
}

test('product price change log is scoped to the authenticated users supplier', function () {
    $ownSupplier = Supplier::factory()->create(['supplier_name' => 'Kausar Oil']);
    $otherSupplier = Supplier::factory()->create(['supplier_name' => 'Nestle Pakistan']);
    $ownProduct = Product::factory()->create(['supplier_id' => $ownSupplier->id, 'product_name' => 'Own Product']);
    $otherProduct = Product::factory()->create(['supplier_id' => $otherSupplier->id, 'product_name' => 'Other Product']);

    $this->user->forceFill(['supplier_id' => $ownSupplier->id])->save();

    priceLog($ownProduct);
    priceLog($otherProduct, ['old_price' => 200, 'new_price' => 210]);

    $response = $this->get(route('reports.product-price-change-log.index'));

    $response->assertSuccessful();
    $response->assertSee('Own Product');
    $response->assertDontSee('Other Product');
    expect($response->viewData('supplierId'))->toBe($ownSupplier->id);
    expect($response->viewData('supplierOptions'))->toHaveCount(1);
    expect($response->viewData('supplierOptions')->first()->id)->toBe($ownSupplier->id);
    expect($response->viewData('productOptions'))->toHaveCount(1);
    expect($response->viewData('productOptions')->first()->id)->toBe($ownProduct->id);
});

test('product price change log blocks filtering by another supplier for scoped users', function () {
    $ownSupplier = Supplier::factory()->create();
    $otherSupplier = Supplier::factory()->create();

    $this->user->forceFill(['supplier_id' => $ownSupplier->id])->save();

    $this->get(route('reports.product-price-change-log.index', [
        'filter' => ['supplier_id' => $otherSupplier->id],
    ]))->assertForbidden();
});

test('product price change log blocks filtering by another suppliers product', function () {
    $ownSupplier = Supplier::factory()->create();
    $otherSupplier = Supplier::factory()->create();
    $otherProduct = Product::factory()->create(['supplier_id' => $otherSupplier->id]);

    $this->user->forceFill(['supplier_id' => $ownSupplier->id])->save();

    $this->get(route('reports.product-price-change-log.index', [
        'filter' => ['product_id' => $otherProduct->id],
    ]))->assertForbidden();
});

test('product price change log renders the list with tabs and totals', function () {
    makeAdmin($this->user);
    $product = Product::factory()->create(['product_name' => 'Cooking Oil 1L']);
    priceLog($product);
    priceLog($product, ['price_type' => 'cost_price', 'old_price' => 90, 'new_price' => 80]);

    $response = $this->get(route('reports.product-price-change-log.index'));

    $response->assertSuccessful()
        ->assertSee('Cooking Oil 1L')
        ->assertSee('Selling Price')
        ->assertSee('Cost Price')
        ->assertSee('Product edit');
    expect($response->viewData('stats'))->toMatchArray([
        'total' => 2,
        'increases' => 1,
        'decreases' => 1,
    ]);
    expect($response->viewData('stats')['by_type'])->toMatchArray(['selling_price' => 1, 'cost_price' => 1, 'expiry_price' => 0]);
});

test('product price change log filters by supplier, product and field changed', function () {
    makeAdmin($this->user);
    $supplierA = Supplier::factory()->create();
    $supplierB = Supplier::factory()->create();
    $productA = Product::factory()->create(['supplier_id' => $supplierA->id, 'product_name' => 'Alpha']);
    $productB = Product::factory()->create(['supplier_id' => $supplierB->id, 'product_name' => 'Bravo']);
    priceLog($productA);
    priceLog($productB, ['price_type' => 'cost_price']);

    expect(listedProducts(['supplier_id' => $supplierA->id]))->toBe(['Alpha'])
        ->and(listedProducts(['product_id' => $productB->id]))->toBe(['Bravo'])
        ->and(listedProducts(['price_type' => 'cost_price']))->toBe(['Bravo']);
});

test('product price change log filters by changed by user and date range', function () {
    makeAdmin($this->user);
    $otherUser = User::factory()->create();
    $recent = Product::factory()->create(['product_name' => 'Recent']);
    $old = Product::factory()->create(['product_name' => 'Old']);
    priceLog($recent, ['changed_by' => $otherUser->id]);
    priceLog($old, ['changed_at' => now()->subDays(60)]);

    expect(listedProducts(['changed_by' => $otherUser->id]))->toBe(['Recent'])
        ->and(listedProducts([]))->toBe(['Recent'])
        ->and(listedProducts(['changed_from' => now()->subDays(61)->toDateString(), 'changed_to' => now()->subDays(59)->toDateString()]))->toBe(['Old']);
});

test('product price change log filters by batch code', function () {
    makeAdmin($this->user);
    $product = Product::factory()->create(['product_name' => 'Batched']);
    $other = Product::factory()->create(['product_name' => 'Unbatched']);
    $batch = StockBatch::factory()->create(['product_id' => $product->id, 'batch_code' => 'BATCH-2026-7777']);
    priceLog($product, ['impacted_batch_ids' => [$batch->id], 'impacted_batch_count' => 1]);
    priceLog($other);

    $this->get(route('reports.product-price-change-log.index'))->assertSee('BATCH-2026-7777');
    expect(listedProducts(['batch' => '7777']))->toBe(['Batched'])
        ->and(listedProducts(['batch' => 'NO-SUCH-BATCH']))->toBe([]);
});

test('product price change log filters by free text search', function () {
    makeAdmin($this->user);
    $supplier = Supplier::factory()->create(['supplier_name' => 'Zeta Foods']);
    Product::factory()->create(['product_name' => 'Unused']);
    priceLog(Product::factory()->create(['supplier_id' => $supplier->id, 'product_name' => 'Tea']));
    priceLog(Product::factory()->create(['product_name' => 'Sugar', 'product_code' => 'SKU-SUG-9']));

    expect(listedProducts(['search' => 'zeta']))->toBe(['Tea'])
        ->and(listedProducts(['search' => 'sug-9']))->toBe(['Sugar'])
        ->and(listedProducts(['search' => mb_strtolower($this->user->name)]))->toBe(['Sugar', 'Tea']);
});

test('product price change log filters by source', function () {
    makeAdmin($this->user);
    $edited = Product::factory()->create(['product_name' => 'Edited']);
    $ticketed = Product::factory()->create(['product_name' => 'Ticketed']);
    $adjusted = Product::factory()->create(['product_name' => 'Adjusted']);

    $ticket = Ticket::factory()->create([
        'type' => TicketType::PriceUpdate,
        'status' => TicketStatus::Approved,
        'ticket_number' => 'TKT-0001',
        'reviewed_by' => $this->user->id,
        'reviewed_at' => now(),
    ]);
    $ticket->items()->create(['product_id' => $ticketed->id, 'old_unit_sell_price' => 100, 'new_unit_sell_price' => 110]);

    $adjustment = StockAdjustment::factory()->posted()->create(['adjustment_number' => 'SA-2026-0042', 'posted_by' => $this->user->id]);
    StockAdjustmentItem::create([
        'stock_adjustment_id' => $adjustment->id,
        'product_id' => $adjusted->id,
        'system_quantity' => 10,
        'actual_quantity' => 10,
        'adjustment_quantity' => 0,
        'unit_cost' => 50,
        'selling_price' => 110,
        'adjustment_value' => 0,
        'uom_id' => Uom::factory()->create()->id,
    ]);

    priceLog($edited, ['changed_at' => now()]);
    priceLog($ticketed, ['changed_at' => now()]);
    priceLog($adjusted, ['changed_at' => now()]);

    expect(listedProducts(['source' => 'ticket']))->toBe(['Ticketed'])
        ->and(listedProducts(['source' => 'stock_adjustment']))->toBe(['Adjusted'])
        ->and(listedProducts(['source' => 'product_edit']))->toBe(['Edited']);

    $this->get(route('reports.product-price-change-log.index'))
        ->assertSee('TKT-0001')
        ->assertSee('SA-2026-0042');
});

test('product price change log keeps the chosen rows per page', function () {
    makeAdmin($this->user);
    $product = Product::factory()->create();
    foreach (range(1, 25) as $i) {
        priceLog($product);
    }

    expect($this->get(route('reports.product-price-change-log.index'))->viewData('logs')->perPage())->toBe(20)
        ->and($this->get(route('reports.product-price-change-log.index', ['per_page' => 50]))->viewData('logs')->count())->toBe(25)
        ->and($this->get(route('reports.product-price-change-log.index', ['per_page' => 7]))->viewData('logs')->perPage())->toBe(20);
});

test('product price change log uses the source recorded on the row before any timing guess', function () {
    makeAdmin($this->user);
    $imported = Product::factory()->create(['product_name' => 'Imported']);
    $ticketed = Product::factory()->create(['product_name' => 'Recorded Ticket']);

    priceLog($imported, ['changed_at' => now(), 'source' => 'import']);
    priceLog($ticketed, ['changed_at' => now(), 'source' => 'ticket', 'source_reference' => 'TKT-000777']);

    expect(listedProducts(['source' => 'import']))->toBe(['Imported'])
        ->and(listedProducts(['source' => 'ticket']))->toBe(['Recorded Ticket'])
        ->and(listedProducts(['source' => 'product_edit']))->toBe([]);

    $this->get(route('reports.product-price-change-log.index'))->assertSee('TKT-000777')->assertSee('Excel import');
});
