<?php

use App\Models\ChartOfAccount;
use App\Models\CurrentStock;
use App\Models\CurrentStockByBatch;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\GoodsIssueItemRemoval;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteItem;
use App\Models\InventoryLedgerEntry;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementItem;
use App\Models\SalesSettlementItemBatch;
use App\Models\StockBatch;
use App\Models\StockMovement;
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
use App\Services\GoodsIssueLineRemovalService;
use App\Services\GoodsIssueReversalService;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;

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
 * Every table a goods issue posting touches, read back for the fixture's batches, products and lines.
 *
 * @return array<string, mixed>
 */
function books(array $fx): array
{
    $issue = $fx['issue']->fresh();
    $names = array_flip($fx['batches']);
    $perBatch = fn (callable $read) => collect($fx['batches'])->map(fn (int $id) => round((float) $read($id), 3))->all();
    $lines = $issue->items()->orderBy('line_no')->get();
    $journalIds = JournalEntry::where('status', 'posted')
        ->where(fn ($query) => $query->where('reference', 'like', $issue->issue_number.'%')->orWhere('reference', 'like', 'REV-'.$issue->issue_number.'%'))
        ->pluck('id');
    $gl = fn (string $code) => round((float) DB::table('journal_entry_details')
        ->whereIn('journal_entry_id', $journalIds)
        ->where('chart_of_account_id', ChartOfAccount::where('account_code', $code)->value('id'))
        ->sum(DB::raw('debit - credit')), 2);

    return [
        'warehouse' => $perBatch(fn ($id) => CurrentStockByBatch::where('stock_batch_id', $id)->where('warehouse_id', $fx['warehouse']->id)->value('quantity_on_hand')),
        'layers' => $perBatch(fn ($id) => StockValuationLayer::where('stock_batch_id', $id)->where('warehouse_id', $fx['warehouse']->id)->sum('quantity_remaining')),
        'current_stock' => [
            'P' => round((float) CurrentStock::where('product_id', $fx['p']->id)->where('warehouse_id', $fx['warehouse']->id)->value('quantity_on_hand'), 3),
            'Q' => round((float) CurrentStock::where('product_id', $fx['q']->id)->where('warehouse_id', $fx['warehouse']->id)->value('quantity_on_hand'), 3),
        ],
        'ledger_warehouse' => $perBatch(fn ($id) => InventoryLedgerEntry::where('stock_batch_id', $id)->where('warehouse_id', $fx['warehouse']->id)->sum(DB::raw('debit_qty - credit_qty'))),
        'ledger_van' => $perBatch(fn ($id) => InventoryLedgerEntry::where('stock_batch_id', $id)->where('vehicle_id', $fx['vehicle']->id)->sum(DB::raw('debit_qty - credit_qty'))),
        'van' => collect(['P' => $fx['p'], 'Q' => $fx['q']])->map(function (Product $product) use ($fx) {
            $balance = VanStockBalance::where('vehicle_id', $fx['vehicle']->id)->where('product_id', $product->id)->first();

            return [round((float) $balance?->quantity_on_hand, 3), round((float) $balance?->quantity_on_hand * (float) $balance?->average_cost, 1)];
        })->all(),
        'van_batches' => $lines->map(fn (GoodsIssueItem $line) => round((float) VanStockBatch::where('goods_issue_item_id', $line->id)->sum('quantity_on_hand'), 3))->all(),
        'moved' => $lines->map(fn (GoodsIssueItem $line) => StockMovement::where('reference_type', GoodsIssue::class)->where('reference_id', $issue->id)
            ->where('goods_issue_item_id', $line->id)->where('movement_type', 'transfer')
            ->selectRaw('stock_batch_id, SUM(-quantity) as net')->groupBy('stock_batch_id')->get()
            ->filter(fn ($row) => (float) $row->net > 0.0005)
            ->mapWithKeys(fn ($row) => [$names[$row->stock_batch_id] => round((float) $row->net, 3)])->sortKeys()->all())->all(),
        'gl' => ['1151' => $gl('1151'), '1155' => $gl('1155')],
        'lines' => $lines->map(fn (GoodsIssueItem $line) => [round((float) $line->quantity_issued, 3), round((float) $line->total_value, 2)])->all(),
        'issue_total' => round((float) $issue->total_value, 2),
        'removals' => GoodsIssueItemRemoval::where('goods_issue_id', $issue->id)->count(),
    ];
}

function remover(): GoodsIssueLineRemovalService
{
    return app(GoodsIssueLineRemovalService::class);
}

it("treats -R journal references as the issue's own", function () {
    $issue = GoodsIssue::factory()->create(['issue_number' => 'GI-2026-0042']);

    expect($issue->ownsJournalReference('GI-2026-0042-R1'))->toBeTrue()
        ->and($issue->ownsJournalReference('GI-2026-0042-S2'))->toBeTrue()
        ->and($issue->ownsJournalReference('GI-2026-0042'))->toBeTrue()
        ->and($issue->ownsJournalReference('GI-2026-00421-R1'))->toBeFalse()
        ->and($issue->ownsJournalReference('GI-2026-0042-X1'))->toBeFalse();
});

it('seeds the remove-items permission for admins', function () {
    expect(Permission::where('name', 'goods-issue-remove-items')->where('guard_name', 'web')->exists())->toBeTrue();
});

it("removes part of a line from the batch issued last, at that batch's cost, in every table", function () {
    $fx = issueFromTwoBatches();

    expect(books($fx))->toMatchArray([
        'warehouse' => ['A' => 0.0, 'B' => 4.0, 'C' => 5.0],
        'van' => ['P' => [16.0, 172.0], 'Q' => [5.0, 40.0]],
        'gl' => ['1151' => -212.0, '1155' => 212.0],
    ]);

    $result = remover()->remove($fx['line1'], 4, 'Four cartons came back unopened');

    expect($result['success'])->toBeTrue()
        ->and($result['removal']->batches)->toEqual([['stock_batch_id' => $fx['batches']['B'], 'batch_code' => StockBatch::find($fx['batches']['B'])->batch_code, 'quantity' => 4.0, 'unit_cost' => 12.0]]);

    expect(books($fx))->toBe([
        'warehouse' => ['A' => 0.0, 'B' => 8.0, 'C' => 5.0],
        'layers' => ['A' => 0.0, 'B' => 8.0, 'C' => 5.0],
        'current_stock' => ['P' => 8.0, 'Q' => 5.0],
        'ledger_warehouse' => ['A' => 0.0, 'B' => 8.0, 'C' => 5.0],
        'ledger_van' => ['A' => 10.0, 'B' => 2.0, 'C' => 5.0],
        'van' => ['P' => [12.0, 124.0], 'Q' => [5.0, 40.0]],
        'van_batches' => [12.0, 5.0],
        'moved' => [['A' => 10.0, 'B' => 2.0], ['C' => 5.0]],
        'gl' => ['1151' => -164.0, '1155' => 164.0],
        'lines' => [[12.0, 186.0], [5.0, 55.0]],
        'issue_total' => 241.0,
        'removals' => 1,
    ]);

    $journal = JournalEntry::where('reference', $fx['issue']->issue_number.'-R1')->firstOrFail();
    expect($journal->status)->toBe('posted')
        ->and($journal->entry_date->toDateString())->toBe($fx['issue']->issue_date->toDateString())
        ->and($result['removal']->journal_entry_id)->toBe($journal->id)
        ->and((float) $result['removal']->cost_value)->toBe(48.0)
        ->and((float) $result['removal']->selling_value)->toBe(72.0);
});

it('removes a whole line and leaves the books as if it had never been posted', function () {
    $fx = issueFromTwoBatches();

    expect(remover()->remove($fx['line1'], 16, 'Wrong SKU loaded')['success'])->toBeTrue();

    expect(books($fx))->toMatchArray([
        'warehouse' => ['A' => 10.0, 'B' => 10.0, 'C' => 5.0],
        'layers' => ['A' => 10.0, 'B' => 10.0, 'C' => 5.0],
        'current_stock' => ['P' => 20.0, 'Q' => 5.0],
        'ledger_warehouse' => ['A' => 10.0, 'B' => 10.0, 'C' => 5.0],
        'ledger_van' => ['A' => 0.0, 'B' => 0.0, 'C' => 5.0],
        'van' => ['P' => [0.0, 0.0], 'Q' => [5.0, 40.0]],
        'van_batches' => [0.0, 5.0],
        'moved' => [[], ['C' => 5.0]],
        'gl' => ['1151' => -40.0, '1155' => 40.0],
        'lines' => [[0.0, 0.0], [5.0, 55.0]],
        'issue_total' => 55.0,
    ]);
});

it('works on what is left when the same line is removed twice', function () {
    $fx = issueFromTwoBatches();

    expect(remover()->remove($fx['line1'], 4, 'First return')['success'])->toBeTrue()
        ->and(remover()->remove($fx['line1']->fresh(), 8, 'Second return')['success'])->toBeTrue();

    expect(books($fx))->toMatchArray([
        'warehouse' => ['A' => 6.0, 'B' => 10.0, 'C' => 5.0],
        'moved' => [['A' => 4.0], ['C' => 5.0]],
        'van' => ['P' => [4.0, 40.0], 'Q' => [5.0, 40.0]],
        'gl' => ['1151' => -80.0, '1155' => 80.0],
        'removals' => 2,
    ]);
    expect(JournalEntry::where('reference', 'like', $fx['issue']->issue_number.'-R%')->orderBy('id')->pluck('reference')->all())
        ->toBe([$fx['issue']->issue_number.'-R1', $fx['issue']->issue_number.'-R2']);
});

it('takes a decimal quantity and then exactly what is left', function () {
    $fx = issueFromTwoBatches();

    expect(remover()->remove($fx['line1'], 2.5, 'Half a carton back')['success'])->toBeTrue()
        ->and(remover()->remove($fx['line1']->fresh(), 13.5, 'The rest back')['success'])->toBeTrue();

    expect(books($fx))->toMatchArray([
        'warehouse' => ['A' => 10.0, 'B' => 10.0, 'C' => 5.0],
        'moved' => [[], ['C' => 5.0]],
        'van' => ['P' => [0.0, 0.0], 'Q' => [5.0, 40.0]],
        'lines' => [[0.0, 0.0], [5.0, 55.0]],
    ]);
});

it('touches only its own line when the same product sits on two lines', function () {
    $fx = issueFromTwoBatches(samePLine: true);

    expect(remover()->remove($fx['line1'], 2, 'Two back from line 1')['success'])->toBeTrue();

    expect(books($fx))->toMatchArray([
        'warehouse' => ['A' => 0.0, 'B' => 4.0, 'C' => 5.0],
        'moved' => [['A' => 10.0, 'B' => 4.0], ['C' => 5.0], ['B' => 2.0]],
        'van_batches' => [14.0, 5.0, 2.0],
        'lines' => [[14.0, 222.0], [5.0, 55.0], [2.0, 36.0]],
    ]);
});

it('refuses and writes nothing', function (Closure $arrange, Closure $act, string $message) {
    $fx = issueFromTwoBatches();
    $arrange($fx);
    $before = books($fx);

    $result = $act($fx);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain($message)
        ->and(books($fx))->toBe($before);
})->with([
    'a settlement exists' => [
        fn (array $fx) => SalesSettlement::factory()->create(['goods_issue_id' => $fx['issue']->id, 'vehicle_id' => $fx['vehicle']->id, 'status' => 'draft']),
        fn (array $fx) => remover()->remove($fx['line1'], 4, 'Too late'),
        'has been made against it',
    ],
    'more than is standing' => [
        fn (array $fx) => null,
        fn (array $fx) => remover()->remove($fx['line1'], 17, 'Too much'),
        'Only 16 is left on this line',
    ],
    "the issue's last quantity" => [
        fn (array $fx) => remover()->remove($fx['line1'], 16, 'Line 1 gone'),
        fn (array $fx) => remover()->remove($fx['line2'], 5, 'Everything'),
        'Use Reverse & Re-issue',
    ],
    'the van holds less' => [
        fn (array $fx) => VanStockBalance::where('vehicle_id', $fx['vehicle']->id)->where('product_id', $fx['p']->id)->update(['quantity_on_hand' => 3]),
        fn (array $fx) => remover()->remove($fx['line1'], 4, 'Van short'),
        'The van holds',
    ],
    'the journal was already reversed' => [
        fn (array $fx) => app(AccountingService::class)->reverseJournalEntry(JournalEntry::where('reference', $fx['issue']->issue_number)->value('id'), 'Manual reversal', now()->toDateString()),
        fn (array $fx) => remover()->remove($fx['line1'], 4, 'After a reversal'),
        'already been reversed',
    ],
    'a zero quantity' => [
        fn (array $fx) => null,
        fn (array $fx) => remover()->remove($fx['line1'], 0, 'Nothing'),
        'more than zero',
    ],
    'a stale line after another removal' => [
        fn (array $fx) => remover()->remove($fx['line1'], 10, 'First person'),
        fn (array $fx) => remover()->remove($fx['line1'], 10, 'Second person, stale page'),
        'Only 6 is left on this line',
    ],
]);

it('refuses a draft issue', function () {
    $fx = issueFromTwoBatches();
    $draft = GoodsIssue::factory()->create(['status' => 'draft']);
    $line = GoodsIssueItem::factory()->create(['goods_issue_id' => $draft->id, 'product_id' => $fx['p']->id, 'uom_id' => $fx['line1']->uom_id, 'quantity_issued' => 5]);

    expect(remover()->remove($line, 1, 'Draft')['message'])->toContain('Only a posted (issued) goods issue');
});

it('shows the settlement form the reduced line and only the batches still on the van', function () {
    $fx = issueFromTwoBatches();
    remover()->remove($fx['line1'], 4, 'Four back');
    $fx['user']->givePermissionTo(Permission::firstOrCreate(['name' => 'sales-settlement-create', 'guard_name' => 'web']));

    $lines = Pest\Laravel\getJson(route('api.sales-settlements.goods-issues.items', $fx['issue']->id))->assertOk()->json('items');
    $line1 = collect($lines)->firstWhere('id', $fx['line1']->id);

    expect((float) $line1['quantity_issued'])->toBe(12.0)
        ->and(collect($line1['batch_breakdown'])->mapWithKeys(fn ($batch) => [$batch['stock_batch_id'] => (float) $batch['quantity']])->all())
        ->toBe([$fx['batches']['A'] => 10.0, $fx['batches']['B'] => 2.0]);
});

it('leaves a fully removed line out of the settlement form', function () {
    $fx = issueFromTwoBatches();
    remover()->remove($fx['line1'], 16, 'Whole line back');
    $fx['user']->givePermissionTo(Permission::firstOrCreate(['name' => 'sales-settlement-create', 'guard_name' => 'web']));

    $lines = Pest\Laravel\getJson(route('api.sales-settlements.goods-issues.items', $fx['issue']->id))->assertOk()->json('items');

    expect(collect($lines)->pluck('id')->all())->toBe([$fx['line2']->id]);
});

it('shows net batches on the issue page after a removal', function () {
    $fx = issueFromTwoBatches();
    remover()->remove($fx['line1'], 4, 'Four back');

    Pest\Laravel\get(route('goods-issues.show', $fx['issue']))
        ->assertOk()
        ->assertSee('<b>2</b> &times; 18.00', false)
        ->assertDontSee('<b>6</b> &times; 18.00', false);
});

it('balances the books when a partly removed issue is then reversed', function () {
    $fx = issueFromTwoBatches();
    expect(remover()->remove($fx['line1'], 4, 'Four back')['success'])->toBeTrue();

    $result = app(GoodsIssueReversalService::class)->reverse($fx['issue']->fresh(), 'Whole issue was wrong after all');

    expect($result['success'])->toBeTrue();
    expect(books($fx))->toMatchArray([
        'warehouse' => ['A' => 10.0, 'B' => 10.0, 'C' => 10.0],
        'layers' => ['A' => 10.0, 'B' => 10.0, 'C' => 10.0],
        'current_stock' => ['P' => 20.0, 'Q' => 10.0],
        'ledger_warehouse' => ['A' => 10.0, 'B' => 10.0, 'C' => 10.0],
        'ledger_van' => ['A' => 0.0, 'B' => 0.0, 'C' => 0.0],
        'van' => ['P' => [0.0, 0.0], 'Q' => [0.0, 0.0]],
        'moved' => [[], []],
        'gl' => ['1151' => 0.0, '1155' => 0.0],
    ]);
    expect($result['replacement']->items()->orderBy('line_no')->pluck('quantity_issued')->map(fn ($q) => (float) $q)->all())->toBe([12.0, 5.0]);
});

it('settles the reduced quantity and empties the van', function () {
    $fx = issueFromTwoBatches();
    expect(remover()->remove($fx['line1'], 4, 'Four back')['success'])->toBeTrue();

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

    $settlement = SalesSettlement::create([
        'settlement_number' => 'SETTLE-TEST-0501', 'settlement_date' => now(), 'employee_id' => $fx['issue']->employee_id,
        'vehicle_id' => $fx['vehicle']->id, 'warehouse_id' => $fx['warehouse']->id, 'goods_issue_id' => $fx['issue']->id,
        'status' => 'draft', 'cash_sales_amount' => 241, 'total_sales_amount' => 241, 'cash_collected' => 241,
    ]);
    foreach ([[$fx['line1'], $fx['p'], 12, 186.0, 124.0], [$fx['line2'], $fx['q'], 5, 55.0, 40.0]] as [$line, $product, $quantity, $sales, $cogs]) {
        $settlementItem = SalesSettlementItem::create([
            'sales_settlement_id' => $settlement->id, 'goods_issue_item_id' => $line->id, 'product_id' => $product->id,
            'quantity_issued' => $quantity, 'quantity_sold' => $quantity, 'quantity_returned' => 0, 'quantity_shortage' => 0,
            'unit_selling_price' => round($sales / $quantity, 4), 'total_sales_value' => $sales,
            'unit_cost' => round($cogs / $quantity, 4), 'total_cogs' => $cogs,
        ]);
        // The settlement form sends the line's net batches (after the removal: P = A 10 + B 2).
        foreach ($line->fresh()->netIssuedBatches() as $batch) {
            SalesSettlementItemBatch::create([
                'sales_settlement_item_id' => $settlementItem->id, 'stock_batch_id' => $batch->stock_batch_id, 'batch_code' => $batch->batch_code,
                'quantity_issued' => $batch->quantity, 'quantity_sold' => $batch->quantity, 'quantity_returned' => 0, 'quantity_shortage' => 0,
                'unit_cost' => $batch->unit_cost, 'selling_price' => $batch->selling_price, 'is_promotional' => (bool) $batch->is_promotional,
            ]);
        }
    }

    $result = app(DistributionService::class)->postSalesSettlement($settlement->fresh());

    expect($result['success'])->toBeTrue($result['message'] ?? '');
    expect(books($fx))->toMatchArray([
        'van' => ['P' => [0.0, 0.0], 'Q' => [0.0, 0.0]],
        'van_batches' => [0.0, 0.0],
    ]);
    expect(books($fx)['ledger_van'])->toBe(['A' => 0.0, 'B' => 0.0, 'C' => 0.0]);
    expect(VanStockBatch::where('vehicle_id', $fx['vehicle']->id)->where('quantity_on_hand', '<', 0)->exists())->toBeFalse();
});

it('removes through the add-or-remove page with a reason and the password', function () {
    $fx = issueFromTwoBatches();

    Pest\Laravel\post(route('goods-issues.remove-item', [$fx['issue'], $fx['line1']]), [
        'quantity' => 4, 'reason' => 'Four cartons came back', 'password' => 'password',
    ])->assertRedirect(route('goods-issues.items', $fx['issue']))
        ->assertSessionHas('success', fn (string $message) => str_contains($message, 'Removed 4 of Olper TBA 250ml from '.$fx['issue']->issue_number)
            && str_contains($message, $fx['issue']->issue_number.'-R1'));

    expect(books($fx)['lines'][0])->toBe([12.0, 186.0]);
});

it('rejects a wrong password and writes nothing', function () {
    $fx = issueFromTwoBatches();
    $before = books($fx);

    Pest\Laravel\post(route('goods-issues.remove-item', [$fx['issue'], $fx['line1']]), [
        'quantity' => 4, 'reason' => 'Four cartons came back', 'password' => 'wrong-password',
    ])->assertSessionHas('error', fn (string $message) => str_contains($message, 'Invalid password'));

    expect(books($fx))->toBe($before);
});

it('forbids removing without the permission and hides the Remove column', function () {
    $fx = issueFromTwoBatches();
    $clerk = User::factory()->create();
    $clerk->givePermissionTo(['goods-issue-list', 'goods-issue-edit']);
    actingAs($clerk);

    Pest\Laravel\post(route('goods-issues.remove-item', [$fx['issue'], $fx['line1']]), [
        'quantity' => 4, 'reason' => 'Not allowed here', 'password' => 'password',
    ])->assertForbidden();
    Pest\Laravel\get(route('goods-issues.items', $fx['issue']))->assertOk()->assertDontSee('aria-label="Remove from', false);
});

it('lets a user who may only remove open the page', function () {
    $fx = issueFromTwoBatches();
    $keeper = User::factory()->create();
    $keeper->givePermissionTo(['goods-issue-list', 'goods-issue-remove-items']);
    actingAs($keeper);

    Pest\Laravel\get(route('goods-issues.items', $fx['issue']))->assertOk()->assertSee('aria-label="Remove from', false);
});

it('refuses when a draft settlement appeared after the page was opened', function () {
    $fx = issueFromTwoBatches();
    SalesSettlement::factory()->create(['goods_issue_id' => $fx['issue']->id, 'vehicle_id' => $fx['vehicle']->id, 'status' => 'draft']);

    Pest\Laravel\post(route('goods-issues.remove-item', [$fx['issue'], $fx['line1']]), [
        'quantity' => 4, 'reason' => 'Opened before the settlement', 'password' => 'password',
    ])->assertSessionHas('error', fn (string $message) => str_contains($message, 'has been made against it'));

    expect(books($fx)['lines'][0])->toBe([16.0, 258.0]);
});

it('redirects the old append-items address to the items page', function () {
    $fx = issueFromTwoBatches();

    Pest\Laravel\get('/goods-issues/'.$fx['issue']->id.'/append-items?from=list')
        ->assertStatus(301)
        ->assertRedirect(route('goods-issues.items', $fx['issue']).'?from=list');
});

it('shows Remove on each line of the add-or-remove page and the renamed button on the issue', function () {
    $fx = issueFromTwoBatches();

    Pest\Laravel\get(route('goods-issues.items', $fx['issue']))
        ->assertOk()
        ->assertSee('Add or remove items · '.$fx['issue']->issue_number)
        ->assertSee('aria-label="Remove from '.$fx['issue']->issue_number.' : Olper TBA 250ml"', false)
        ->assertSee('aria-label="Remove from '.$fx['issue']->issue_number.' : Tarang 220ml"', false);
    expect(remover()->lineRefusal($fx['line1']))->toBeNull()
        ->and(remover()->lineRefusal($fx['line2']))->toBeNull();
    Pest\Laravel\get(route('goods-issues.items', $fx['issue']))->assertDontSee('must be more than zero');
    // The sheet locks its submit button once sent, so a double click cannot remove the quantity twice.
    Pest\Laravel\get(route('goods-issues.items', $fx['issue']))
        ->assertSee('@submit="submitting = true"', false)
        ->assertSee(':disabled="submitting || ', false);
    Pest\Laravel\get(route('goods-issues.show', $fx['issue']))->assertOk()->assertSee('Add or remove items');
});

it('lists the removal journal and the removal on the issue page', function () {
    $fx = issueFromTwoBatches();
    remover()->remove($fx['line1'], 4, 'Four cartons came back');

    Pest\Laravel\get(route('goods-issues.show', $fx['issue']))
        ->assertOk()
        ->assertSee($fx['issue']->issue_number.'-R1')
        ->assertSee('Removed 4 of Olper TBA 250ml: Four cartons came back');
});

it('accepts at most two decimals, as stock movements keep', function () {
    $fx = issueFromTwoBatches();

    Pest\Laravel\post(route('goods-issues.remove-item', [$fx['issue'], $fx['line1']]), [
        'quantity' => 2.125, 'reason' => 'Three decimals', 'password' => 'password',
    ])->assertSessionHasErrors('quantity');
});

it('does not ask the settlement for a line whose whole quantity was removed', function () {
    $fx = issueFromTwoBatches();
    remover()->remove($fx['line1'], 16, 'Whole line back');
    $issue = $fx['issue']->fresh();

    expect($issue->settlementLinesMismatch([['goods_issue_item_id' => $fx['line2']->id, 'quantity_issued' => 5]]))->toBeNull()
        ->and($issue->settlementLinesMismatch([]))->toBeNull()
        ->and($issue->settlementLinesMismatch([['goods_issue_item_id' => $fx['line1']->id, 'quantity_issued' => 0]]))
        ->toContain('line 2 (Tarang 220ml, 5) is missing');
});

it('removes only once when the same form arrives twice', function () {
    $fx = issueFromTwoBatches();
    $payload = ['quantity' => 4, 'reason' => 'Four cartons came back', 'password' => 'password', '_once' => 'remove-form-1'];

    Pest\Laravel\post(route('goods-issues.remove-item', [$fx['issue'], $fx['line1']]), $payload)
        ->assertSessionHas('success');
    Pest\Laravel\post(route('goods-issues.remove-item', [$fx['issue'], $fx['line1']]), $payload)
        ->assertSessionHas('error', fn (string $message) => str_contains($message, 'already been sent'));

    expect(books($fx)['lines'][0])->toBe([12.0, 186.0])
        ->and(books($fx)['removals'])->toBe(1);
});

it('gives the add, remove, create and reverse forms a one-time token and a locking button', function () {
    $fx = issueFromTwoBatches();
    $fx['user']->givePermissionTo(Permission::firstOrCreate(['name' => 'goods-issue-reverse', 'guard_name' => 'web']));

    Pest\Laravel\get(route('goods-issues.items', $fx['issue']))->assertOk()->assertSee('name="_once"', false);
    Pest\Laravel\get(route('goods-issues.show', $fx['issue']))->assertOk()
        ->assertSee('name="_once"', false)
        ->assertSee('Reversing…');
});
