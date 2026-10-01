<?php

use App\Models\AccountType;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\CurrentStock;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementCashDenomination;
use App\Models\SalesSettlementItem;
use App\Models\SalesSettlementItemBatch;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\VanStockBalance;
use App\Models\VanStockBatch;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\DistributionService;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
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

it('stays in balance from issue through added items to the posted settlement', function () {
    Notification::fake();
    $data = postGoodsIssueForAppending();
    $currencyId = Currency::where('is_base_currency', true)->value('id');
    $typeId = AccountType::firstOrCreate(['type_name' => 'Assets'], ['report_group' => 'BalanceSheet'])->id;
    foreach (['1121' => 'Cash', '1122' => 'Cheques In Hand', '1123' => 'Salesman Clearing', '1111' => 'Debtors', '4110' => 'Sales'] as $code => $name) {
        ChartOfAccount::firstOrCreate(['account_code' => $code], [
            'account_name' => $name, 'account_type_id' => $typeId, 'currency_id' => $currencyId, 'normal_balance' => 'debit', 'is_active' => true,
        ]);
    }
    // The settlement entry books sales to cost center 4 and stock to cost center 6.
    foreach ([4 => 'CC004', 6 => 'CC006-SETTLE'] as $id => $code) {
        DB::table('cost_centers')->insert(['id' => $id, 'code' => $code, 'name' => $code, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }
    post(route('goods-issues.store-appended-items', $data['goodsIssue']), appendPayload($data, 10));
    [$mainLine, $addedLine] = $data['goodsIssue']->items()->orderBy('line_no')->get()->all();
    $settlement = SalesSettlement::factory()->create([
        'goods_issue_id' => $data['goodsIssue']->id,
        'vehicle_id' => $data['vehicle']->id,
        'warehouse_id' => $data['goodsIssue']->warehouse_id,
        'employee_id' => $data['goodsIssue']->employee_id,
        'settlement_date' => $data['goodsIssue']->issue_date,
        'cash_sales_amount' => 750,
        'total_sales_amount' => 750,
    ]);
    // As the settlement form sends it: each line with the batch it was loaded from.
    foreach ([[$mainLine, 18, 18, 0, 10], [$addedLine, 10, 7, 3, 20]] as [$line, $issued, $sold, $returned, $cost]) {
        $batch = StockBatch::where('product_id', $data['product']->id)->where('unit_cost', $cost)->firstOrFail();
        $settlementItem = SalesSettlementItem::factory()->create([
            'sales_settlement_id' => $settlement->id,
            'goods_issue_item_id' => $line->id,
            'product_id' => $data['product']->id,
            'quantity_issued' => $issued,
            'quantity_sold' => $sold,
            'quantity_returned' => $returned,
            'quantity_shortage' => 0,
            'unit_selling_price' => 30,
            'total_sales_value' => $sold * 30,
            'unit_cost' => 0,
            'total_cogs' => 0,
        ]);
        SalesSettlementItemBatch::create([
            'sales_settlement_item_id' => $settlementItem->id,
            'stock_batch_id' => $batch->id,
            'batch_code' => $batch->batch_code,
            'quantity_issued' => $issued,
            'quantity_sold' => $sold,
            'quantity_returned' => $returned,
            'quantity_shortage' => 0,
            'unit_cost' => $cost,
            'selling_price' => 30,
            'is_promotional' => false,
        ]);
    }
    SalesSettlementCashDenomination::create([
        'sales_settlement_id' => $settlement->id,
        'denom_5000' => 0, 'denom_1000' => 0, 'denom_500' => 1, 'denom_100' => 2, 'denom_50' => 1,
        'denom_20' => 0, 'denom_10' => 0, 'denom_coins' => 0, 'total_amount' => 750,
    ]);

    $result = app(DistributionService::class)->postSalesSettlement($settlement->fresh());

    expect($result['success'])->toBeTrue($result['message'] ?? '')
        ->and((float) VanStockBalance::where('vehicle_id', $data['vehicle']->id)->value('quantity_on_hand'))->toBe(0.0)
        ->and((float) VanStockBatch::where('vehicle_id', $data['vehicle']->id)->sum('quantity_on_hand'))->toBe(0.0)
        ->and((float) CurrentStock::where('product_id', $data['product']->id)->value('quantity_on_hand'))->toBe(93.0)
        ->and(Artisan::call('inventory:verify-consistency'))->toBe(0)
        ->and(Artisan::call('accounting:reconcile-stock-gl', ['--tolerance' => 0.01]))->toBe(0);

    $vanStockOnIssueDate = (float) DB::table('journal_entry_details as line')
        ->join('journal_entries as entry', 'entry.id', '=', 'line.journal_entry_id')
        ->join('chart_of_accounts as account', 'account.id', '=', 'line.chart_of_account_id')
        ->where('entry.status', 'posted')
        ->where('account.account_code', '1155')
        ->whereDate('entry.entry_date', '<=', $data['goodsIssue']->issue_date)
        ->sum(DB::raw('line.debit - line.credit'));
    expect($vanStockOnIssueDate)->toBe(0.0);
});
