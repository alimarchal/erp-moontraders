<?php

use App\Models\ChartOfAccount;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteItem;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementItem;
use App\Models\SalesSettlementItemBatch;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\DistributionService;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;

/*
 * Shared fixtures for goods issue and sales settlement tests: a real posted goods issue and a postable
 * draft settlement against it, so several test files can exercise the posting services end to end.
 */

/**
 * Product P received twice (batch A: 10 @ cost 10, sell 15; batch B: 10 @ cost 12, sell 18) and product Q once
 * (batch C: 10 @ cost 8, sell 11). The issue posts line 1 = P 16 (A 10 + B 6) and line 2 = Q 5 (C 5);
 * with $samePLine a line 3 = P 2 follows (B 2).
 *
 * @return array{user: User, issue: GoodsIssue, line1: GoodsIssueItem, line2: GoodsIssueItem, line3: ?GoodsIssueItem, p: Product, q: Product, warehouse: Warehouse, vehicle: Vehicle, batches: array{A: int, B: int, C: int}}
 */
function issueFromTwoBatches(bool $samePLine = false): array
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $permissions = ['goods-issue-list', 'goods-issue-edit', 'goods-issue-remove-items'];
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);
    seedGrnPostingAccounts();

    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $vehicle = Vehicle::factory()->create(['vehicle_number' => 'RIS-1569', 'supplier_id' => $supplier->id]);
    $employee = Employee::factory()->create(['supplier_id' => $supplier->id]);
    $uom = Uom::factory()->create();
    $p = Product::factory()->create(['product_name' => 'Olper TBA 250ml', 'uom_conversion_factor' => 1]);
    $q = Product::factory()->create(['product_name' => 'Tarang 220ml', 'uom_conversion_factor' => 1]);

    actingAs($user);

    $receive = function (Product $product, float $cost, float $sell, int $daysAgo) use ($supplier, $warehouse, $uom): int {
        $grn = GoodsReceiptNote::factory()->create([
            'supplier_id' => $supplier->id, 'warehouse_id' => $warehouse->id,
            'receipt_date' => now()->subDays($daysAgo), 'status' => 'draft',
        ]);
        GoodsReceiptNoteItem::factory()->create([
            'grn_id' => $grn->id, 'product_id' => $product->id, 'stock_uom_id' => $uom->id, 'purchase_uom_id' => $uom->id,
            'qty_in_purchase_uom' => 1, 'uom_conversion_factor' => 1, 'qty_in_stock_uom' => 1, 'quantity_ordered' => 1,
            'quantity_received' => 10, 'quantity_accepted' => 10, 'unit_cost' => $cost, 'selling_price' => $sell,
            'is_promotional' => false, 'priority_order' => 1,
        ]);
        expect(app(InventoryService::class)->postGrnToInventory($grn->fresh())['success'])->toBeTrue();

        return (int) StockBatch::where('product_id', $product->id)->latest('id')->value('id');
    };

    $batches = ['A' => $receive($p, 10, 15, 3), 'B' => $receive($p, 12, 18, 2), 'C' => $receive($q, 8, 11, 2)];

    $issue = GoodsIssue::factory()->create([
        'issue_number' => 'GI-'.now()->year.'-0501', 'issue_date' => now()->toDateString(),
        'warehouse_id' => $warehouse->id, 'vehicle_id' => $vehicle->id, 'employee_id' => $employee->id,
        'supplier_id' => $supplier->id, 'issued_by' => $user->id, 'status' => 'draft',
    ]);
    $lines = [[$p, 16, 258.00], [$q, 5, 55.00]];
    if ($samePLine) {
        $lines[] = [$p, 2, 36.00];
    }
    foreach ($lines as $index => [$product, $quantity, $value]) {
        GoodsIssueItem::factory()->create([
            'goods_issue_id' => $issue->id, 'line_no' => $index + 1, 'product_id' => $product->id, 'uom_id' => $uom->id,
            'quantity_issued' => $quantity, 'unit_cost' => 10, 'selling_price' => round($value / $quantity, 4), 'total_value' => $value,
        ]);
    }
    expect(app(DistributionService::class)->postGoodsIssue($issue->fresh())['success'])->toBeTrue();

    $items = $issue->items()->orderBy('line_no')->get();

    return [
        'user' => $user, 'issue' => $issue->fresh(), 'line1' => $items[0], 'line2' => $items[1], 'line3' => $items[2] ?? null,
        'p' => $p, 'q' => $q, 'warehouse' => $warehouse, 'vehicle' => $vehicle, 'batches' => $batches,
    ];
}

/**
 * Accounts and cost centres a settlement posting needs, on top of seedGrnPostingAccounts().
 */
function seedSettlementPostingAccounts(): void
{
    $template = ChartOfAccount::where('account_code', '1151')->firstOrFail();

    for ($id = 1; $id <= 10; $id++) {
        DB::table('cost_centers')->insertOrIgnore(['id' => $id, 'name' => "Cost Center {$id}", 'code' => "CC-{$id}", 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    foreach (['1121', '1122', '1123', '1171', '1111', '1141', '1161', '4110', '5111', '5272', '5252', '5262', '5292', '5282', '5223', '5213', '5271', '4250'] as $code) {
        ChartOfAccount::firstOrCreate(['account_code' => $code], [
            'account_name' => "Account {$code}", 'account_type_id' => $template->account_type_id,
            'currency_id' => $template->currency_id, 'is_active' => true, 'normal_balance' => 'debit',
        ]);
    }
}

/**
 * A draft settlement of the fixture's issue that sells every unit still on each line for cash, with the line's net
 * batches, ready for DistributionService::postSalesSettlement().
 *
 * @param  array{issue: GoodsIssue, vehicle: Vehicle, warehouse: Warehouse}  $fx
 */
function draftSettlementForIssue(array $fx, string $number = 'SETTLE-TEST-0601'): SalesSettlement
{
    seedSettlementPostingAccounts();

    $lines = $fx['issue']->items()->orderBy('line_no')->get()->filter(fn (GoodsIssueItem $line) => (float) $line->quantity_issued > 0.0005);
    $total = round((float) $lines->sum(fn (GoodsIssueItem $line) => $line->netIssuedBatches()->sum(fn ($b) => $b->quantity * $b->selling_price)), 2);

    $settlement = SalesSettlement::create([
        'settlement_number' => $number, 'settlement_date' => now(), 'employee_id' => $fx['issue']->employee_id,
        'vehicle_id' => $fx['vehicle']->id, 'warehouse_id' => $fx['warehouse']->id, 'goods_issue_id' => $fx['issue']->id,
        'status' => 'draft', 'cash_sales_amount' => $total, 'total_sales_amount' => $total, 'cash_collected' => $total,
    ]);

    foreach ($lines as $line) {
        $batches = $line->netIssuedBatches();
        $quantity = (float) $batches->sum('quantity');
        $sales = round((float) $batches->sum(fn ($b) => $b->quantity * $b->selling_price), 2);
        $cogs = round((float) $batches->sum(fn ($b) => $b->quantity * $b->unit_cost), 2);

        $item = SalesSettlementItem::create([
            'sales_settlement_id' => $settlement->id, 'goods_issue_item_id' => $line->id, 'product_id' => $line->product_id,
            'quantity_issued' => $quantity, 'quantity_sold' => $quantity, 'quantity_returned' => 0, 'quantity_shortage' => 0,
            'unit_selling_price' => round($sales / $quantity, 4), 'total_sales_value' => $sales,
            'unit_cost' => round($cogs / $quantity, 4), 'total_cogs' => $cogs,
        ]);

        foreach ($batches as $batch) {
            SalesSettlementItemBatch::create([
                'sales_settlement_item_id' => $item->id, 'stock_batch_id' => $batch->stock_batch_id, 'batch_code' => $batch->batch_code,
                'quantity_issued' => $batch->quantity, 'quantity_sold' => $batch->quantity, 'quantity_returned' => 0, 'quantity_shortage' => 0,
                'unit_cost' => $batch->unit_cost, 'selling_price' => $batch->selling_price, 'is_promotional' => (bool) $batch->is_promotional,
            ]);
        }
    }

    return $settlement->fresh();
}
