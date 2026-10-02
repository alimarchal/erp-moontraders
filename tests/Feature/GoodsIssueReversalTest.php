<?php

use App\Models\CurrentStock;
use App\Models\CurrentStockByBatch;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteItem;
use App\Models\InventoryLedgerEntry;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\StockValuationLayer;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\VanStockBalance;
use App\Models\VanStockBatch;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\AccountingService;
use App\Services\DistributionService;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

uses(RefreshDatabase::class);

/**
 * 100 units received at cost 10 (selling 15), then GI-YYYY-2107 posted to the wrong van:
 * two lines of the same product, 10 + 8 units.
 *
 * @return array{user: User, product: Product, warehouse: Warehouse, supplier: Supplier, wrongVehicle: Vehicle, rightVehicle: Vehicle, rightEmployee: Employee, goodsIssue: GoodsIssue}
 */
function postGoodsIssueToWrongVan(array $permissions = ['goods-issue-list', 'goods-issue-edit', 'goods-issue-post', 'goods-issue-reverse']): array
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $user = User::factory()->create();

    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $user->givePermissionTo($permissions);

    seedGrnPostingAccounts();

    $product = Product::factory()->create(['product_name' => 'Olper TBA 250ml']);
    $warehouse = Warehouse::factory()->create();
    $supplier = Supplier::factory()->create();
    $wrongVehicle = Vehicle::factory()->create(['vehicle_number' => 'RIS-1569', 'supplier_id' => $supplier->id]);
    $rightVehicle = Vehicle::factory()->create(['vehicle_number' => 'RIS-2421', 'supplier_id' => $supplier->id]);
    $wrongEmployee = Employee::factory()->create(['supplier_id' => $supplier->id]);
    $rightEmployee = Employee::factory()->create(['supplier_id' => $supplier->id]);
    $uom = Uom::factory()->create();

    $grn = GoodsReceiptNote::factory()->create([
        'supplier_id' => $supplier->id,
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
        'quantity_ordered' => 1,
        'quantity_received' => 100,
        'quantity_accepted' => 100,
        'unit_cost' => 10.00,
        'selling_price' => 15.00,
        'is_promotional' => false,
        'priority_order' => 1,
    ]);

    actingAs($user);

    expect(app(InventoryService::class)->postGrnToInventory($grn->fresh())['success'])->toBeTrue();

    $goodsIssue = GoodsIssue::factory()->create([
        'issue_number' => 'GI-'.now()->year.'-2107',
        'issue_date' => now()->toDateString(),
        'warehouse_id' => $warehouse->id,
        'vehicle_id' => $wrongVehicle->id,
        'employee_id' => $wrongEmployee->id,
        'supplier_id' => $supplier->id,
        'issued_by' => $user->id,
        'status' => 'draft',
    ]);

    foreach ([10, 8] as $quantity) {
        GoodsIssueItem::factory()->create([
            'goods_issue_id' => $goodsIssue->id,
            'product_id' => $product->id,
            'uom_id' => $uom->id,
            'quantity_issued' => $quantity,
            'unit_cost' => 10.00,
            'selling_price' => 15.00,
            'total_value' => $quantity * 15,
        ]);
    }

    expect(app(DistributionService::class)->postGoodsIssue($goodsIssue->fresh())['success'])->toBeTrue();

    return compact('user', 'product', 'warehouse', 'supplier', 'wrongVehicle', 'rightVehicle', 'rightEmployee') + ['goodsIssue' => $goodsIssue->fresh()];
}

it('reverses a posted goods issue, returns its stock, offsets its entries and opens a corrected draft', function () {
    Notification::fake();
    $data = postGoodsIssueToWrongVan();
    $goodsIssue = $data['goodsIssue'];

    $response = post(route('goods-issues.reverse', $goodsIssue), [
        'reason' => 'Posted to the wrong salesman and vehicle',
        'password' => 'password',
    ]);

    $replacement = GoodsIssue::where('replaces_goods_issue_id', $goodsIssue->id)->firstOrFail();
    $response->assertRedirect(route('goods-issues.edit', $replacement));

    $goodsIssue->refresh();
    expect($goodsIssue->status)->toBe('cancelled')
        ->and($goodsIssue->active_vehicle_lock)->toBeNull()
        ->and($goodsIssue->reversed_by)->toBe($data['user']->id)
        ->and($goodsIssue->reversal_reason)->toBe('Posted to the wrong salesman and vehicle');

    expect((float) CurrentStock::where('product_id', $data['product']->id)->value('quantity_on_hand'))->toBe(100.0)
        ->and((float) CurrentStockByBatch::where('product_id', $data['product']->id)->value('quantity_on_hand'))->toBe(100.0)
        ->and((float) StockValuationLayer::where('product_id', $data['product']->id)->sum('quantity_remaining'))->toBe(100.0)
        ->and((float) VanStockBalance::where('vehicle_id', $data['wrongVehicle']->id)->value('quantity_on_hand'))->toBe(0.0)
        ->and((float) VanStockBatch::where('vehicle_id', $data['wrongVehicle']->id)->sum('quantity_on_hand'))->toBe(0.0);

    $reversal = JournalEntry::with('details.account')->where('reference', 'REV-GI-'.now()->year.'-2107')->firstOrFail();
    expect($reversal->status)->toBe('posted')
        ->and($reversal->entry_date->toDateString())->toBe($goodsIssue->issue_date->toDateString())
        ->and($reversal->details->mapWithKeys(fn ($line) => [$line->account->account_code => (float) $line->debit - (float) $line->credit])->all())
        ->toEqual(['1151' => 180.0, '1155' => -180.0]);

    $ledgerNet = InventoryLedgerEntry::where('goods_issue_id', $goodsIssue->id)
        ->selectRaw('COALESCE(warehouse_id, 0) as warehouse, COALESCE(vehicle_id, 0) as vehicle, SUM(debit_qty - credit_qty) as net')
        ->groupByRaw('COALESCE(warehouse_id, 0), COALESCE(vehicle_id, 0)')
        ->pluck('net')
        ->map(fn ($net) => (float) $net)
        ->all();
    expect($ledgerNet)->toEqual([0.0, 0.0]);

    expect($replacement->issue_number)->toBe('GI-'.now()->year.'-2108')
        ->and($replacement->status)->toBe('draft')
        ->and($replacement->active_vehicle_lock)->toBe($data['wrongVehicle']->id)
        ->and($replacement->items)->toHaveCount(1)
        ->and((float) $replacement->items->first()->quantity_issued)->toBe(18.0);

    expect(Artisan::call('inventory:verify-consistency'))->toBe(0)
        ->and(Artisan::call('accounting:reconcile-stock-gl', ['--tolerance' => 0.01]))->toBe(0);
});

it('lets the replacement draft be pointed at the right van and posted', function () {
    $data = postGoodsIssueToWrongVan();
    post(route('goods-issues.reverse', $data['goodsIssue']), ['reason' => 'Wrong salesman and van', 'password' => 'password']);
    $replacement = GoodsIssue::where('replaces_goods_issue_id', $data['goodsIssue']->id)->firstOrFail();
    $replacement->update(['vehicle_id' => $data['rightVehicle']->id, 'employee_id' => $data['rightEmployee']->id]);

    $result = app(DistributionService::class)->postGoodsIssue($replacement->fresh());

    expect($result['success'])->toBeTrue($result['message'])
        ->and((float) VanStockBalance::where('vehicle_id', $data['rightVehicle']->id)->value('quantity_on_hand'))->toBe(18.0)
        ->and((float) CurrentStock::where('product_id', $data['product']->id)->value('quantity_on_hand'))->toBe(82.0)
        ->and(Artisan::call('inventory:verify-consistency'))->toBe(0);
});

it('refuses to reverse an issue that has a settlement, and changes nothing', function () {
    $data = postGoodsIssueToWrongVan();
    $settlement = SalesSettlement::factory()->create([
        'goods_issue_id' => $data['goodsIssue']->id,
        'vehicle_id' => $data['wrongVehicle']->id,
        'warehouse_id' => $data['warehouse']->id,
    ]);

    post(route('goods-issues.reverse', $data['goodsIssue']), ['reason' => 'Wrong salesman and van', 'password' => 'password'])
        ->assertSessionHas('error', fn (string $message) => str_contains($message, "Settlement {$settlement->settlement_number} (draft) has been made against it"));

    expect($data['goodsIssue']->fresh()->status)->toBe('issued')
        ->and((float) VanStockBalance::where('vehicle_id', $data['wrongVehicle']->id)->value('quantity_on_hand'))->toBe(18.0);
    assertDatabaseMissing('goods_issues', ['replaces_goods_issue_id' => $data['goodsIssue']->id]);
    assertDatabaseMissing('journal_entries', ['reference' => 'REV-GI-'.now()->year.'-2107']);
});

it('refuses to reverse when part of the stock has already left the van', function () {
    $data = postGoodsIssueToWrongVan();
    VanStockBalance::where('vehicle_id', $data['wrongVehicle']->id)->update(['quantity_on_hand' => 12]);

    post(route('goods-issues.reverse', $data['goodsIssue']), ['reason' => 'Wrong salesman and van', 'password' => 'password'])
        ->assertSessionHas('error', fn (string $message) => str_contains($message, 'The van holds 12 of Olper TBA 250ml but this issue loaded 18'));

    expect($data['goodsIssue']->fresh()->status)->toBe('issued');
});

it('refuses a wrong password without reversing', function () {
    $data = postGoodsIssueToWrongVan();

    post(route('goods-issues.reverse', $data['goodsIssue']), ['reason' => 'Wrong salesman and van', 'password' => 'not-my-password'])
        ->assertSessionHas('error', 'Invalid password. Reversing a goods issue requires your password confirmation.');

    expect($data['goodsIssue']->fresh()->status)->toBe('issued');
});

it('requires a reason', function () {
    $data = postGoodsIssueToWrongVan();

    post(route('goods-issues.reverse', $data['goodsIssue']), ['reason' => '', 'password' => 'password'])
        ->assertSessionHasErrors(['reason' => 'The reason field is required.']);

    expect($data['goodsIssue']->fresh()->status)->toBe('issued');
});

it('forbids users without the reverse permission', function () {
    $data = postGoodsIssueToWrongVan(['goods-issue-list', 'goods-issue-edit', 'goods-issue-post']);

    post(route('goods-issues.reverse', $data['goodsIssue']), ['reason' => 'Wrong salesman and van', 'password' => 'password'])
        ->assertForbidden();

    expect($data['goodsIssue']->fresh()->status)->toBe('issued');
});

it('links the reversed issue and its replacement on both show pages', function () {
    $data = postGoodsIssueToWrongVan();
    $year = now()->year;

    get(route('goods-issues.show', $data['goodsIssue']))->assertSee('Reverse &amp; Re-issue', false);

    post(route('goods-issues.reverse', $data['goodsIssue']), ['reason' => 'Wrong salesman and van', 'password' => 'password']);
    $replacement = GoodsIssue::where('replaces_goods_issue_id', $data['goodsIssue']->id)->firstOrFail();

    get(route('goods-issues.show', $data['goodsIssue']))
        ->assertSee('Reason: Wrong salesman and van')
        ->assertSee("Replaced by GI-{$year}-2108")
        ->assertSee("REV-GI-{$year}-2107")
        ->assertDontSee('Reverse &amp; Re-issue', false);
    get(route('goods-issues.show', $replacement))->assertSee("Copied from reversed <b>GI-{$year}-2107</b>", false);
});

it('refuses to settle a goods issue that has been reversed', function () {
    $data = postGoodsIssueToWrongVan(['goods-issue-list', 'goods-issue-reverse', 'sales-settlement-create']);
    $goodsIssueItem = $data['goodsIssue']->items->first();
    post(route('goods-issues.reverse', $data['goodsIssue']), ['reason' => 'Wrong salesman and van', 'password' => 'password']);

    post(route('sales-settlements.store'), [
        'settlement_date' => now()->toDateString(),
        'goods_issue_id' => $data['goodsIssue']->id,
        'items' => [[
            'product_id' => $data['product']->id,
            'goods_issue_item_id' => $goodsIssueItem->id,
            'quantity_issued' => 10,
            'quantity_sold' => 10,
            'unit_cost' => 10,
            'selling_price' => 15,
        ]],
    ])->assertSessionHas('error', 'Goods Issue GI-'.now()->year.'-2107 is reversed and cannot be settled.');

    assertDatabaseMissing('sales_settlements', ['goods_issue_id' => $data['goodsIssue']->id]);
});

it('refuses to reverse an issue whose journal entry accounts already reversed by hand', function () {
    $data = postGoodsIssueToWrongVan();
    $entry = JournalEntry::where('reference', $data['goodsIssue']->issue_number)->firstOrFail();
    $manual = app(AccountingService::class)->reverseJournalEntry($entry->id);

    post(route('goods-issues.reverse', $data['goodsIssue']), ['reason' => 'Wrong salesman and van', 'password' => 'password'])
        ->assertSessionHas('error', fn (string $message) => str_contains($message, "already been reversed by entry #{$manual['data']->id}"));

    expect($data['goodsIssue']->fresh()->status)->toBe('issued')
        ->and(JournalEntry::where('reference', 'REV-'.$entry->reference)->count())->toBe(1);
});

it('does not reverse the same journal entry twice', function () {
    $data = postGoodsIssueToWrongVan();
    $entry = JournalEntry::where('reference', $data['goodsIssue']->issue_number)->firstOrFail();
    $accounting = app(AccountingService::class);
    $first = $accounting->reverseJournalEntry($entry->id);

    $second = $accounting->reverseJournalEntry($entry->id);

    expect($second['success'])->toBeFalse()
        ->and($second['message'])->toContain("has already been reversed by entry #{$first['data']->id}");
});

it('merges a product to one draft line at the weighted rate and leaves out unposted lines', function () {
    $data = postGoodsIssueToWrongVan();
    $uomId = $data['goodsIssue']->items->first()->uom_id;
    $supplementary = GoodsIssueItem::factory()->create([
        'goods_issue_id' => $data['goodsIssue']->id,
        'product_id' => $data['product']->id,
        'uom_id' => $uomId,
        'quantity_issued' => 4,
        'unit_cost' => 10.00,
        'selling_price' => 16.00,
        'total_value' => 64,
        'is_supplementary' => true,
    ]);
    expect(app(DistributionService::class)->postSupplementaryItems($data['goodsIssue']->fresh(), collect([$supplementary]))['success'])->toBeTrue();
    GoodsIssueItem::factory()->create([
        'goods_issue_id' => $data['goodsIssue']->id,
        'product_id' => $data['product']->id,
        'uom_id' => $uomId,
        'quantity_issued' => 50,
        'unit_cost' => 10.00,
        'selling_price' => 15.00,
        'is_supplementary' => true,
    ]);

    post(route('goods-issues.reverse', $data['goodsIssue']), ['reason' => 'Wrong salesman and van', 'password' => 'password']);

    $replacement = GoodsIssue::with('items')->where('replaces_goods_issue_id', $data['goodsIssue']->id)->firstOrFail();
    expect($replacement->items->map(fn ($item) => [(float) $item->quantity_issued, (float) $item->selling_price, (float) $item->total_value])->all())
        ->toEqual([[22.0, 15.18, 333.96]])
        ->and((float) $replacement->total_quantity)->toBe(22.0)
        ->and((float) $replacement->total_value)->toBe(333.96);
});

it('names the active issue that holds a vehicle when a draft is moved onto it', function () {
    $data = postGoodsIssueToWrongVan(['goods-issue-list', 'goods-issue-edit', 'goods-issue-reverse']);
    post(route('goods-issues.reverse', $data['goodsIssue']), ['reason' => 'Wrong salesman and van', 'password' => 'password']);
    $replacement = GoodsIssue::with('items')->where('replaces_goods_issue_id', $data['goodsIssue']->id)->firstOrFail();
    $blocking = GoodsIssue::factory()->create(['vehicle_id' => $data['rightVehicle']->id, 'warehouse_id' => $data['warehouse']->id, 'status' => 'draft']);
    $line = $replacement->items->first();

    put(route('goods-issues.update', $replacement), [
        'issue_date' => $replacement->issue_date->toDateString(),
        'warehouse_id' => $data['warehouse']->id,
        'vehicle_id' => $data['rightVehicle']->id,
        'employee_id' => $data['rightEmployee']->id,
        'items' => [[
            'product_id' => $line->product_id,
            'quantity_issued' => 18,
            'unit_cost' => 10,
            'selling_price' => 15,
            'uom_id' => $line->uom_id,
        ]],
    ])->assertSessionHasErrors(['vehicle_id' => "This vehicle already has an active Goods Issue ({$blocking->issue_number}). Post its settlement, or delete it if it is a draft, before moving this issue onto the vehicle."]);

    expect($replacement->fresh()->vehicle_id)->toBe($data['wrongVehicle']->id);
});

it('closes its transaction when a settlement already exists for the issue', function () {
    $data = postGoodsIssueToWrongVan(['goods-issue-list', 'sales-settlement-create']);
    $existing = SalesSettlement::factory()->create(['goods_issue_id' => $data['goodsIssue']->id, 'vehicle_id' => $data['wrongVehicle']->id]);
    $levelBefore = DB::transactionLevel();

    post(route('sales-settlements.store'), [
        'settlement_date' => now()->toDateString(),
        'goods_issue_id' => $data['goodsIssue']->id,
        'items' => [[
            'product_id' => $data['product']->id,
            'quantity_issued' => 18,
            'quantity_sold' => 18,
            'unit_cost' => 10,
            'selling_price' => 15,
        ]],
    ])->assertRedirect(route('sales-settlements.show', $existing));

    expect(DB::transactionLevel())->toBe($levelBefore);
});

it('saves a draft that keeps its own vehicle, since the vehicle lock it holds is its own', function () {
    $data = postGoodsIssueToWrongVan(['goods-issue-list', 'goods-issue-edit', 'goods-issue-reverse']);
    post(route('goods-issues.reverse', $data['goodsIssue']), ['reason' => 'Wrong salesman and van', 'password' => 'password']);
    $replacement = GoodsIssue::with('items')->where('replaces_goods_issue_id', $data['goodsIssue']->id)->firstOrFail();
    $line = $replacement->items->first();

    put(route('goods-issues.update', $replacement), [
        'issue_date' => $replacement->issue_date->toDateString(),
        'warehouse_id' => $data['warehouse']->id,
        'vehicle_id' => $replacement->vehicle_id,
        'employee_id' => $replacement->employee_id,
        'items' => [[
            'product_id' => $line->product_id,
            'quantity_issued' => 20,
            'unit_cost' => 10,
            'selling_price' => 15,
            'uom_id' => $line->uom_id,
        ]],
    ])->assertSessionHasNoErrors()
        ->assertRedirect(route('goods-issues.show', $replacement))
        ->assertSessionHas('success');

    expect((float) $replacement->fresh()->items()->sum('quantity_issued'))->toBe(20.0);
});
