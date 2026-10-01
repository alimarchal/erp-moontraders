<?php

use App\Models\CurrentStock;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\VanStockBalance;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\DistributionService;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

/**
 * 18 units received at cost 10 and 100 at cost 20, then a goods issue dated yesterday
 * posted with the 18 cheaper units.
 *
 * @return array{product: Product, uom: Uom, vehicle: Vehicle, goodsIssue: GoodsIssue}
 */
function postGoodsIssueForAppending(): array
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user = User::factory()->create();
    foreach (['goods-issue-list', 'goods-issue-edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $user->givePermissionTo(['goods-issue-list', 'goods-issue-edit']);
    seedGrnPostingAccounts();
    actingAs($user);

    $product = Product::factory()->create(['product_name' => 'Olper TBA 250ml']);
    $warehouse = Warehouse::factory()->create();
    $supplier = Supplier::factory()->create();
    $vehicle = Vehicle::factory()->create(['supplier_id' => $supplier->id]);
    $uom = Uom::factory()->create();

    foreach ([[18, 10.00, 1, 3], [100, 20.00, 2, 2]] as [$quantity, $cost, $priority, $daysAgo]) {
        $grn = GoodsReceiptNote::factory()->create([
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'receipt_date' => now()->subDays($daysAgo),
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
            'quantity_ordered' => 1,
            'quantity_received' => $quantity,
            'quantity_accepted' => $quantity,
            'unit_cost' => $cost,
            'selling_price' => 30.00,
            'is_promotional' => false,
            'priority_order' => $priority,
        ]);
        expect(app(InventoryService::class)->postGrnToInventory($grn->fresh())['success'])->toBeTrue();
    }

    $goodsIssue = GoodsIssue::factory()->create([
        'issue_date' => now()->subDay()->toDateString(),
        'warehouse_id' => $warehouse->id,
        'vehicle_id' => $vehicle->id,
        'employee_id' => Employee::factory()->create(['supplier_id' => $supplier->id])->id,
        'supplier_id' => $supplier->id,
        'issued_by' => $user->id,
        'status' => 'draft',
    ]);
    GoodsIssueItem::factory()->create([
        'goods_issue_id' => $goodsIssue->id,
        'product_id' => $product->id,
        'uom_id' => $uom->id,
        'quantity_issued' => 18,
        'unit_cost' => 10.00,
        'selling_price' => 30.00,
        'total_value' => 540,
    ]);
    expect(app(DistributionService::class)->postGoodsIssue($goodsIssue->fresh())['success'])->toBeTrue();

    return compact('product', 'uom', 'vehicle') + ['goodsIssue' => $goodsIssue->fresh()];
}

/**
 * @return array{items: list<array<string, mixed>>}
 */
function appendPayload(array $data, float $quantity): array
{
    return ['items' => [[
        'product_id' => $data['product']->id,
        'quantity_issued' => $quantity,
        'unit_cost' => 20,
        'selling_price' => 30,
        'uom_id' => $data['uom']->id,
    ]]];
}

it('posts appended items on the issue date and averages the van cost', function () {
    $data = postGoodsIssueForAppending();

    post(route('goods-issues.store-appended-items', $data['goodsIssue']), appendPayload($data, 10))
        ->assertRedirect(route('goods-issues.show', $data['goodsIssue']));

    $supplementaryEntry = JournalEntry::where('reference', $data['goodsIssue']->issue_number.'-S1')->firstOrFail();
    expect($supplementaryEntry->entry_date->toDateString())->toBe(now()->subDay()->toDateString());

    $balance = VanStockBalance::where('vehicle_id', $data['vehicle']->id)->firstOrFail();
    expect((float) $balance->quantity_on_hand)->toBe(28.0)
        ->and((float) $balance->average_cost)->toBe(13.57);
});

it('leaves no line behind when the appended items cannot be posted', function () {
    $data = postGoodsIssueForAppending();
    $data['goodsIssue']->update(['issue_date' => now()->subMonthsNoOverflow(3)->toDateString()]);

    post(route('goods-issues.store-appended-items', $data['goodsIssue']), appendPayload($data, 10))
        ->assertSessionHas('error', fn (string $message) => str_starts_with($message, 'Unable to append items: Failed to post supplementary items:'));

    expect(GoodsIssueItem::where('goods_issue_id', $data['goodsIssue']->id)->where('is_supplementary', true)->exists())->toBeFalse()
        ->and((float) CurrentStock::where('product_id', $data['product']->id)->value('quantity_on_hand'))->toBe(100.0)
        ->and((float) $data['goodsIssue']->fresh()->total_quantity)->toBe((float) $data['goodsIssue']->total_quantity);
});
