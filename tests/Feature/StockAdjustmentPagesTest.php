<?php

use App\Models\AccountingPeriod;
use App\Models\AccountType;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\Currency;
use App\Models\CurrentStockByBatch;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\StockValuationLayer;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Every stock-adjustments page in the Goods Issues layout: the list with each filter, create, store,
 * show, edit, update, post and destroy.
 */
beforeEach(function () {
    $permissions = ['stock-adjustment-list', 'stock-adjustment-create', 'stock-adjustment-edit', 'stock-adjustment-delete', 'stock-adjustment-post'];
    foreach ($permissions as $permission) {
        Permission::create(['name' => $permission]);
    }

    $this->user = User::factory()->create(['name' => 'Store Keeper']);
    $this->user->givePermissionTo($permissions);
    $this->poster = User::factory()->create(['name' => 'Chief Poster']);
    $this->actingAs($this->user);

    $this->supplier = Supplier::factory()->create(['supplier_name' => 'Nestle Pakistan Ltd', 'disabled' => false]);
    $this->otherSupplier = Supplier::factory()->create(['supplier_name' => 'Engro Foods', 'disabled' => false]);
    $this->warehouse = Warehouse::factory()->create(['warehouse_name' => 'Main Warehouse']);
    $this->otherWarehouse = Warehouse::factory()->create(['warehouse_name' => 'Branch Store']);
    $this->uom = Uom::factory()->create();
    $this->product = Product::factory()->create(['supplier_id' => $this->supplier->id, 'product_name' => 'Milkpak 1L', 'product_code' => 'MPK-1000']);
    $this->otherProduct = Product::factory()->create(['supplier_id' => $this->otherSupplier->id, 'product_name' => 'Olpers 250ml', 'product_code' => 'OLP-250']);
    $this->batch = StockBatch::factory()->create(['product_id' => $this->product->id, 'batch_code' => 'BATCH-MILK-01', 'status' => 'active', 'is_active' => true, 'unit_cost' => 50, 'selling_price' => 80]);
    $this->otherBatch = StockBatch::factory()->create(['product_id' => $this->otherProduct->id, 'batch_code' => 'BATCH-OLP-77', 'status' => 'active', 'is_active' => true, 'unit_cost' => 50]);

    $currency = Currency::factory()->base()->create();
    $expense = AccountType::create(['type_name' => 'Expense', 'report_group' => 'IncomeStatement']);
    $asset = AccountType::create(['type_name' => 'Asset', 'report_group' => 'BalanceSheet']);
    ChartOfAccount::create(['account_code' => '1151', 'account_name' => 'Stock In Hand', 'account_type_id' => $asset->id, 'currency_id' => $currency->id, 'is_active' => true, 'normal_balance' => 'debit']);
    foreach (['5280' => 'Stock Loss on Recalls', '5281' => 'Stock Loss - Damage', '5282' => 'Stock Loss - Theft', '5283' => 'Stock Loss - Expiry', '5284' => 'Stock Loss - Other'] as $code => $name) {
        ChartOfAccount::create(['account_code' => $code, 'account_name' => $name, 'account_type_id' => $expense->id, 'currency_id' => $currency->id, 'is_active' => true, 'normal_balance' => 'debit']);
    }
    CostCenter::create(['code' => 'CC006', 'name' => 'Warehouse & Inventory', 'is_active' => true]);
    AccountingPeriod::create(['name' => 'Test Period', 'start_date' => now()->subYear()->toDateString(), 'end_date' => now()->addYear()->toDateString(), 'status' => 'open']);

    // The damage draft: Milkpak at the main warehouse, created by the store keeper.
    $this->damage = StockAdjustment::create([
        'adjustment_number' => 'SA-PAGE-DAMAGE', 'adjustment_date' => now()->subDays(2)->toDateString(), 'warehouse_id' => $this->warehouse->id,
        'adjustment_type' => 'damage', 'reason' => 'Rack collapsed', 'status' => 'draft', 'created_by' => $this->user->id,
    ]);
    $this->damage->items()->create([
        'product_id' => $this->product->id, 'stock_batch_id' => $this->batch->id, 'system_quantity' => 100, 'actual_quantity' => 90,
        'adjustment_quantity' => -10, 'unit_cost' => 50, 'adjustment_value' => -500, 'uom_id' => $this->uom->id,
    ]);

    // The posted theft: Olpers at the branch store, a month ago, posted by someone else.
    $this->theft = StockAdjustment::create([
        'adjustment_number' => 'SA-PAGE-THEFT', 'adjustment_date' => now()->subMonth()->toDateString(), 'warehouse_id' => $this->otherWarehouse->id,
        'adjustment_type' => 'theft', 'reason' => 'Missing carton', 'status' => 'posted', 'created_by' => $this->poster->id,
        'posted_by' => $this->poster->id, 'posted_at' => now()->subMonth(),
    ]);
    $this->theft->items()->create([
        'product_id' => $this->otherProduct->id, 'stock_batch_id' => $this->otherBatch->id, 'system_quantity' => 20, 'actual_quantity' => 17,
        'adjustment_quantity' => -3, 'unit_cost' => 50, 'adjustment_value' => -150, 'uom_id' => $this->uom->id,
    ]);
    // Creating stamps the signed-in user as creator, so the other creator is set directly.
    StockAdjustment::whereKey($this->theft->id)->update(['created_by' => $this->poster->id]);
});

/**
 * Stock the batch the way a GRN would have, so a draft against it can be posted.
 */
function stockForPagesTest(object $context, StockBatch $batch, float $quantity): void
{
    $receipt = StockMovement::create([
        'movement_type' => 'grn', 'reference_type' => 'App\\Models\\GoodsReceiptNote', 'reference_id' => $batch->id,
        'movement_date' => now()->toDateString(), 'product_id' => $batch->product_id, 'stock_batch_id' => $batch->id,
        'warehouse_id' => $context->warehouse->id, 'quantity' => $quantity, 'uom_id' => $context->uom->id,
        'unit_cost' => 50, 'total_value' => $quantity * 50, 'created_by' => $context->user->id,
    ]);
    StockValuationLayer::create([
        'product_id' => $batch->product_id, 'warehouse_id' => $context->warehouse->id, 'stock_batch_id' => $batch->id,
        'stock_movement_id' => $receipt->id, 'receipt_date' => now()->toDateString(), 'quantity_received' => $quantity,
        'quantity_remaining' => $quantity, 'unit_cost' => 50, 'total_value' => $quantity * 50, 'value_remaining' => $quantity * 50,
        'priority_order' => $batch->priority_order ?? 99,
    ]);
    CurrentStockByBatch::create([
        'product_id' => $batch->product_id, 'warehouse_id' => $context->warehouse->id, 'stock_batch_id' => $batch->id,
        'quantity_on_hand' => $quantity, 'unit_cost' => 50, 'selling_price' => 80, 'total_value' => $quantity * 50, 'status' => 'active',
    ]);
}

test('the list shows status tabs, the filter panel, totals and both adjustments', function () {
    $this->get(route('stock-adjustments.index'))
        ->assertOk()
        ->assertSee('Stock Adjustments')
        ->assertSee('id="gl-filter-panel"', false)
        ->assertSee('More filters')
        ->assertSee('name="filter[supplier_id]"', false)
        ->assertSee('name="filter[product]"', false)
        ->assertSee('name="filter[batch]"', false)
        ->assertSee('name="filter[created_by]"', false)
        ->assertSee('name="filter[posted_by]"', false)
        ->assertSee('name="per_page"', false)
        ->assertSee('How it works')
        ->assertSeeInOrder(['All', '2', 'Draft', '1', 'Posted', '1', 'Cancelled', '0'])
        ->assertSee('SA-PAGE-DAMAGE')
        ->assertSee('SA-PAGE-THEFT')
        ->assertSee('-650')
        ->assertViewHas('totals', fn (array $totals) => $totals['quantity'] === -13.0 && $totals['value'] === -650.0);
});

test('each list filter narrows the adjustments to the matching one', function (array $filter, string $shown, string $hidden) {
    $filter = collect($filter)->map(fn ($value) => is_string($value) && str_starts_with($value, '@') ? data_get($this, substr($value, 1)) : $value)->all();

    $this->get(route('stock-adjustments.index', ['filter' => $filter]))
        ->assertOk()
        ->assertSee($shown)
        ->assertDontSee($hidden);
})->with([
    'search by number' => [['search' => 'damage'], 'SA-PAGE-DAMAGE', 'SA-PAGE-THEFT'],
    'search by reason' => [['search' => 'missing carton'], 'SA-PAGE-THEFT', 'SA-PAGE-DAMAGE'],
    'adjustment number' => [['adjustment_number' => 'THEFT'], 'SA-PAGE-THEFT', 'SA-PAGE-DAMAGE'],
    'status tab' => [['status' => 'posted'], 'SA-PAGE-THEFT', 'SA-PAGE-DAMAGE'],
    'adjustment type' => [['adjustment_type' => 'damage'], 'SA-PAGE-DAMAGE', 'SA-PAGE-THEFT'],
    'warehouse' => [['warehouse_id' => '@otherWarehouse.id'], 'SA-PAGE-THEFT', 'SA-PAGE-DAMAGE'],
    'supplier' => [['supplier_id' => '@supplier.id'], 'SA-PAGE-DAMAGE', 'SA-PAGE-THEFT'],
    'product id' => [['product_id' => '@otherProduct.id'], 'SA-PAGE-THEFT', 'SA-PAGE-DAMAGE'],
    'product name' => [['product' => 'milkpak'], 'SA-PAGE-DAMAGE', 'SA-PAGE-THEFT'],
    'product SKU' => [['product' => 'OLP-250'], 'SA-PAGE-THEFT', 'SA-PAGE-DAMAGE'],
    'batch' => [['batch' => 'milk-01'], 'SA-PAGE-DAMAGE', 'SA-PAGE-THEFT'],
    'created by' => [['created_by' => '@poster.id'], 'SA-PAGE-THEFT', 'SA-PAGE-DAMAGE'],
    'posted by' => [['posted_by' => '@poster.id'], 'SA-PAGE-THEFT', 'SA-PAGE-DAMAGE'],
]);

test('the date filters keep only adjustments inside the range', function () {
    $this->get(route('stock-adjustments.index', ['filter' => ['date_from' => now()->subWeek()->toDateString(), 'date_to' => now()->toDateString()]]))
        ->assertOk()->assertSee('SA-PAGE-DAMAGE')->assertDontSee('SA-PAGE-THEFT');

    $this->get(route('stock-adjustments.index', ['filter' => ['date_to' => now()->subWeeks(2)->toDateString()]]))
        ->assertOk()->assertSee('SA-PAGE-THEFT')->assertDontSee('SA-PAGE-DAMAGE');
});

test('tab counts ignore the status filter and the totals follow the filters', function () {
    $this->get(route('stock-adjustments.index', ['filter' => ['status' => 'draft']]))
        ->assertOk()
        ->assertViewHas('statusCounts', fn ($counts) => (int) $counts['draft'] === 1 && (int) $counts['posted'] === 1)
        ->assertViewHas('totals', fn (array $totals) => $totals['value'] === -500.0);
});

test('rows per page paginates the list and an unknown size falls back to 20', function () {
    $this->get(route('stock-adjustments.index', ['per_page' => 50]))
        ->assertOk()->assertViewHas('perPage', '50')->assertViewHas('adjustments', fn ($page) => $page->perPage() === 50);

    $this->get(route('stock-adjustments.index', ['per_page' => 7]))
        ->assertOk()->assertViewHas('perPage', '20');
});

test('the list downloads as an A4 PDF', function () {
    $this->get(route('stock-adjustments.index', ['format' => 'pdf', 'orientation' => 'landscape']))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('the create page renders the form in the goods issue layout', function () {
    $this->get(route('stock-adjustments.create'))
        ->assertOk()
        ->assertSee('New Stock Adjustment')
        ->assertSee('id="sa-form"', false)
        ->assertSee('Adjustment details')
        ->assertSee('SKUs to adjust')
        ->assertSee('gf-actions', false)
        ->assertSee('Save draft')
        ->assertSee('name="supplier_id"', false)
        ->assertSee('name="warehouse_id"', false)
        ->assertSee('name="adjustment_type"', false)
        ->assertSee('name="reason"', false);
});

test('storing a draft redirects to its page', function () {
    $this->post(route('stock-adjustments.store'), [
        'adjustment_date' => now()->toDateString(), 'supplier_id' => $this->supplier->id, 'warehouse_id' => $this->warehouse->id,
        'adjustment_type' => 'expiry', 'reason' => 'Expired on shelf',
        'items' => [['product_id' => $this->product->id, 'stock_batch_id' => $this->batch->id, 'system_quantity' => 100, 'actual_quantity' => 95, 'unit_cost' => 50, 'uom_id' => $this->uom->id]],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $created = StockAdjustment::firstWhere('reason', 'Expired on shelf');

    expect($created->status)->toBe('draft')
        ->and((float) $created->items()->sole()->adjustment_value)->toBe(-250.0);
});

test('the draft page shows the summary, lines and the edit, delete and post actions', function () {
    $this->get(route('stock-adjustments.show', $this->damage))
        ->assertOk()
        ->assertSee('Stock Adjustment SA-PAGE-DAMAGE')
        ->assertSee('Adjustment summary')
        ->assertSee('Items adjusted')
        ->assertSee('Milkpak 1L')
        ->assertSee('BATCH-MILK-01')
        ->assertSee('Selling cost')
        ->assertSee('Unit cost')
        ->assertSee('-500.00')
        ->assertSee('Nestle Pakistan Ltd')
        ->assertSee(route('stock-adjustments.edit', $this->damage), false)
        ->assertSee(route('stock-adjustments.destroy', $this->damage), false)
        ->assertSee(route('stock-adjustments.post', $this->damage), false)
        ->assertSee('name="password"', false)
        ->assertSee('Timeline');
});

test('the posted page shows who posted it and offers no draft actions', function () {
    $this->get(route('stock-adjustments.show', $this->theft))
        ->assertOk()
        ->assertSee('Posted')
        ->assertSee('Chief Poster')
        ->assertSee('Olpers 250ml')
        ->assertDontSee(route('stock-adjustments.edit', $this->theft), false)
        ->assertDontSee(route('stock-adjustments.post', $this->theft), false);
});

test('the edit page renders the saved draft and a posted one redirects back', function () {
    $this->get(route('stock-adjustments.edit', $this->damage))
        ->assertOk()
        ->assertSee('Edit Stock Adjustment SA-PAGE-DAMAGE')
        ->assertSee('id="sa-form"', false)
        ->assertSee('Rack collapsed')
        ->assertSee('BATCH-MILK-01')
        ->assertSee('Save changes');

    $this->get(route('stock-adjustments.edit', $this->theft))
        ->assertRedirect(route('stock-adjustments.show', $this->theft));
});

test('updating a draft saves the new lines', function () {
    $this->put(route('stock-adjustments.update', $this->damage), [
        'adjustment_date' => now()->toDateString(), 'warehouse_id' => $this->warehouse->id, 'adjustment_type' => 'damage', 'reason' => 'Rack collapsed again',
        'items' => [['product_id' => $this->product->id, 'stock_batch_id' => $this->batch->id, 'system_quantity' => 100, 'actual_quantity' => 80, 'unit_cost' => 50, 'uom_id' => $this->uom->id]],
    ])->assertSessionHasNoErrors()->assertRedirect(route('stock-adjustments.show', $this->damage));

    expect($this->damage->fresh()->reason)->toBe('Rack collapsed again')
        ->and((float) $this->damage->items()->sole()->adjustment_quantity)->toBe(-20.0);
});

test('posting needs the right password and then posts the draft', function () {
    stockForPagesTest($this, $this->batch, 100);

    $this->post(route('stock-adjustments.post', $this->damage), ['password' => 'wrong'])
        ->assertRedirect()->assertSessionHas('error', 'Invalid password');
    expect($this->damage->fresh()->status)->toBe('draft');

    $this->post(route('stock-adjustments.post', $this->damage), ['password' => 'password'])
        ->assertRedirect(route('stock-adjustments.show', $this->damage))
        ->assertSessionHas('success');
    expect($this->damage->fresh()->status)->toBe('posted');
});

test('a draft can be deleted but a posted adjustment cannot', function () {
    $this->delete(route('stock-adjustments.destroy', $this->theft))
        ->assertRedirect(route('stock-adjustments.index'))->assertSessionHas('error');
    expect($this->theft->fresh())->not->toBeNull();

    $this->delete(route('stock-adjustments.destroy', $this->damage))
        ->assertRedirect(route('stock-adjustments.index'))->assertSessionHas('success');
    expect(StockAdjustment::find($this->damage->id))->toBeNull();
});

test('the help page still renders', function () {
    $this->get(route('stock-adjustments.help'))->assertOk()->assertSee('How stock adjustments work');
});

test('a user without the list permission cannot open the list', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('stock-adjustments.index'))
        ->assertForbidden();
});

test('a company user only sees people from their own adjustments in the created / posted by filter', function () {
    $this->user->update(['supplier_id' => $this->supplier->id]);

    $response = $this->get(route('stock-adjustments.index', ['filter' => ['status' => 'all', 'date_from' => now()->subYear()->toDateString()]]))->assertOk();

    expect($response->viewData('people')->pluck('name')->all())->toBe(['Store Keeper'])
        ->and($response->viewData('suppliers')->pluck('id')->all())->toBe([$this->supplier->id]);
});
