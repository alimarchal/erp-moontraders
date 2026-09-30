<?php

use App\Models\AccountingPeriod;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Drafts do not hold stock: they are written first and posted one by one, so stock a draft
 * was counting on can be gone by the time it is posted. These tests cover what the person
 * posting is told about it — by product name, all at once, and where the stock went.
 */
function stockCheckUser(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissions = ['goods-issue-list', 'goods-issue-create', 'goods-issue-edit', 'goods-issue-post'];
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    AccountingPeriod::factory()->create([
        'start_date' => now()->startOfMonth(),
        'end_date' => now()->endOfMonth(),
        'status' => 'open',
    ]);
    seedGrnPostingAccounts();

    return $user;
}

/** Posts a GRN so the warehouse really holds this quantity of the product. */
function receiveStock(User $user, Warehouse $warehouse, Product $product, float $quantity): void
{
    $uom = Uom::factory()->create();
    $grn = GoodsReceiptNote::factory()->create([
        'supplier_id' => Supplier::factory()->create()->id,
        'warehouse_id' => $warehouse->id,
        'receipt_date' => now()->subDay(),
        'status' => 'draft',
    ]);

    GoodsReceiptNoteItem::factory()->create([
        'grn_id' => $grn->id,
        'product_id' => $product->id,
        'stock_uom_id' => $uom->id,
        'purchase_uom_id' => $uom->id,
        'qty_in_purchase_uom' => 1,
        'uom_conversion_factor' => 1,
        'qty_in_stock_uom' => 1,
        'quantity_received' => $quantity,
        'quantity_accepted' => $quantity,
        'unit_cost' => 10.00,
        'selling_price' => 15.00,
        'is_promotional' => false,
    ]);

    auth()->login($user);
    expect(app(InventoryService::class)->postGrnToInventory($grn->fresh())['success'])->toBeTrue();
}

/**
 * @param  array<int, float>  $lines  quantity per product id
 */
function draftIssue(User $user, Warehouse $warehouse, array $lines, ?Vehicle $vehicle = null): GoodsIssue
{
    $issue = GoodsIssue::factory()->create([
        'warehouse_id' => $warehouse->id,
        'vehicle_id' => ($vehicle ?? Vehicle::factory()->create())->id,
        'employee_id' => Employee::factory()->create()->id,
        'issued_by' => $user->id,
        'issue_date' => now(),
        'status' => 'draft',
    ]);

    foreach ($lines as $productId => $quantity) {
        GoodsIssueItem::factory()->create([
            'goods_issue_id' => $issue->id,
            'product_id' => $productId,
            'uom_id' => Uom::factory()->create()->id,
            'quantity_issued' => $quantity,
            'unit_cost' => 10.00,
            'selling_price' => 15.00,
            'exclude_promotional' => false,
        ]);
    }

    return $issue;
}

it('names every product that is short when a draft is posted, in cartons and pieces', function () {
    $user = stockCheckUser();
    $warehouse = Warehouse::factory()->create();
    $cerelac = Product::factory()->create(['product_code' => 'CER-175', 'product_name' => 'Cerelac Rice 48x175g', 'uom_conversion_factor' => 24]);
    $nido = Product::factory()->create(['product_code' => 'NIDO-400', 'product_name' => 'Nido 400g', 'uom_conversion_factor' => 12]);
    $milo = Product::factory()->create(['product_code' => 'MILO-1', 'product_name' => 'Milo 1kg', 'uom_conversion_factor' => 1]);
    receiveStock($user, $warehouse, $cerelac, 240);
    receiveStock($user, $warehouse, $nido, 30);
    receiveStock($user, $warehouse, $milo, 50);
    $issue = draftIssue($user, $warehouse, [$cerelac->id => 408, $nido->id => 36, $milo->id => 10]);

    $response = $this->actingAs($user)->from(route('goods-issues.show', $issue))->post(route('goods-issues.post', $issue));

    $response->assertRedirect(route('goods-issues.show', $issue))
        ->assertSessionHas('error', fn (string $message) => str_contains($message, 'CER-175 – Cerelac Rice 48x175g: needs 17 ctn + 0 pcs (408 pcs), only 10 ctn + 0 pcs (240 pcs) in stock (short 7 ctn + 0 pcs (168 pcs))')
            && str_contains($message, 'NIDO-400 – Nido 400g: needs 3 ctn + 0 pcs (36 pcs), only 2 ctn + 6 pcs (30 pcs) in stock (short 0 ctn + 6 pcs (6 pcs))')
            && ! str_contains($message, 'Milo'));
    expect($issue->fresh()->status)->toBe('draft')
        ->and(StockMovement::where('reference_type', GoodsIssue::class)->where('reference_id', $issue->id)->exists())->toBeFalse();
});

it('counts every line of a product together when the same product is on two lines', function () {
    $user = stockCheckUser();
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create(['product_name' => 'Twin Line Biscuit', 'uom_conversion_factor' => 1]);
    receiveStock($user, $warehouse, $product, 15);
    $issue = draftIssue($user, $warehouse, [$product->id => 10]);
    GoodsIssueItem::factory()->create([
        'goods_issue_id' => $issue->id,
        'product_id' => $product->id,
        'uom_id' => Uom::factory()->create()->id,
        'quantity_issued' => 8,
        'unit_cost' => 10.00,
        'selling_price' => 15.00,
    ]);

    $response = $this->actingAs($user)->from(route('goods-issues.show', $issue))->post(route('goods-issues.post', $issue));

    $response->assertSessionHas('error', fn (string $message) => str_contains($message, 'Twin Line Biscuit: needs 18 pcs, only 15 pcs in stock (short 3 pcs)'));
    expect($issue->fresh()->status)->toBe('draft');
});

it('shows the draft which products are short and which other issues are drawing on them', function () {
    $user = stockCheckUser();
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create(['product_name' => 'Cerelac Wheat', 'uom_conversion_factor' => 1]);
    receiveStock($user, $warehouse, $product, 100);
    $posted = draftIssue($user, $warehouse, [$product->id => 90], Vehicle::factory()->create(['vehicle_number' => 'VAN-POSTED']));
    $this->actingAs($user)->post(route('goods-issues.post', $posted));
    $otherDraft = draftIssue($user, $warehouse, [$product->id => 5], Vehicle::factory()->create(['vehicle_number' => 'VAN-DRAFT']));
    $issue = draftIssue($user, $warehouse, [$product->id => 15]);

    $response = $this->actingAs($user)->get(route('goods-issues.show', $issue));

    $response->assertSeeInOrder([
        'Stock Check before posting',
        '1 product short',
        'Cerelac Wheat',
        '15 pcs',
        '10 pcs',
        "{$otherDraft->issue_number} (VAN-DRAFT): 5 pcs",
        "{$posted->issue_number} (VAN-POSTED): 90 pcs",
        'Short 5 pcs',
    ]);
});

it('tells the draft when every product is in stock', function () {
    $user = stockCheckUser();
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create(['uom_conversion_factor' => 1]);
    receiveStock($user, $warehouse, $product, 100);
    $issue = draftIssue($user, $warehouse, [$product->id => 40]);

    $response = $this->actingAs($user)->get(route('goods-issues.show', $issue));

    $response->assertSee('All 1 products are in stock.');
});

it('reports what other drafts hold of a product, leaving out the draft being edited', function () {
    $user = stockCheckUser();
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create(['uom_conversion_factor' => 1]);
    receiveStock($user, $warehouse, $product, 100);
    $other = draftIssue($user, $warehouse, [$product->id => 30], Vehicle::factory()->create(['vehicle_number' => 'VAN-7']));
    $editing = draftIssue($user, $warehouse, [$product->id => 20]);

    $response = $this->actingAs($user)->getJson(route('api.warehouses.products.stock', [$warehouse, $product]).'?goods_issue_id='.$editing->id);

    $response->assertOk()
        ->assertJsonPath('available_quantity', fn ($quantity) => (float) $quantity === 100.0)
        ->assertJsonPath('in_other_drafts', fn ($quantity) => (float) $quantity === 30.0)
        ->assertJsonPath('other_drafts', [['issue_number' => $other->issue_number, 'vehicle' => 'VAN-7', 'quantity' => 30]]);
});
