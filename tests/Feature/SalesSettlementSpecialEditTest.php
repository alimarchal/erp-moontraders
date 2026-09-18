<?php

use App\Models\AccountingPeriod;
use App\Models\Customer;
use App\Models\CustomerEmployeeAccount;
use App\Models\CustomerEmployeeAccountTransaction;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\InventoryLedgerEntry;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementItem;
use App\Models\SalesSettlementItemBatch;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Uom;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\DistributionService;
use App\Services\SalesSettlementRevertService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ──────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────

function makeSpecialEditSetup(): array
{
    AccountingPeriod::firstOrCreate(
        ['name' => now()->format('F Y')],
        [
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'status' => 'open',
        ]
    );

    $superAdmin = User::factory()->create(['is_super_admin' => 'Yes']);
    $employee = Employee::factory()->create();
    $vehicle = Vehicle::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();

    $goodsIssue = GoodsIssue::factory()->create([
        'status' => 'issued',
        'employee_id' => $employee->id,
        'vehicle_id' => $vehicle->id,
        'warehouse_id' => $warehouse->id,
    ]);

    $settlement = SalesSettlement::factory()->create([
        'status' => 'posted',
        'posted_at' => now(),
        'goods_issue_id' => $goodsIssue->id,
        'employee_id' => $employee->id,
        'vehicle_id' => $vehicle->id,
        'warehouse_id' => $warehouse->id,
        'settlement_date' => now()->toDateString(),
        'notes' => 'Original notes',
    ]);

    SalesSettlementItem::create([
        'sales_settlement_id' => $settlement->id,
        'product_id' => $product->id,
        'quantity_issued' => 10,
        'quantity_sold' => 10,
        'quantity_returned' => 0,
        'quantity_shortage' => 0,
        'unit_selling_price' => 150,
        'total_sales_value' => 1500,
        'unit_cost' => 100,
        'total_cogs' => 1000,
    ]);

    return compact('superAdmin', 'employee', 'vehicle', 'warehouse', 'product', 'goodsIssue', 'settlement');
}

function specialEditPayload(array $setup, array $overrides = []): array
{
    return array_merge([
        'settlement_date' => $setup['settlement']->settlement_date->toDateString(),
        'goods_issue_id' => $setup['goodsIssue']->id,
        'items' => [[
            'product_id' => $setup['product']->id,
            'quantity_issued' => 10,
            'quantity_sold' => 10,
            'quantity_returned' => 0,
            'quantity_shortage' => 0,
            'unit_cost' => 100,
            'selling_price' => 150,
            'batches' => [],
        ]],
        'notes' => 'Corrected notes',
        'denom_5000' => 0, 'denom_1000' => 1, 'denom_500' => 0, 'denom_100' => 0,
        'denom_50' => 0, 'denom_20' => 0, 'denom_10' => 0, 'denom_coins' => 0,
    ], $overrides);
}

// ──────────────────────────────────────────────────────
// Access control
// ──────────────────────────────────────────────────────

it('denies non super admins access to the special edit form', function () {
    $this->actingAs(User::factory()->create(['is_super_admin' => 'No']));

    $settlement = SalesSettlement::factory()->create(['status' => 'posted']);

    $this->get(route('sales-settlements.edit-special', $settlement))->assertForbidden();
});

it('denies non super admins from submitting the special update', function () {
    $setup = makeSpecialEditSetup();
    $this->actingAs(User::factory()->create(['is_super_admin' => 'No']));

    $this->post(route('sales-settlements.update-special', $setup['settlement']), specialEditPayload($setup))
        ->assertForbidden();

    expect($setup['settlement']->fresh()->notes)->toBe('Original notes');
});

it('redirects the special edit form away for draft settlements', function () {
    $setup = makeSpecialEditSetup();
    $setup['settlement']->update(['status' => 'draft']);

    $this->actingAs($setup['superAdmin'])
        ->get(route('sales-settlements.edit-special', $setup['settlement']))
        ->assertRedirect(route('sales-settlements.show', $setup['settlement']))
        ->assertSessionHas('error');
});

it('rejects the special update for a draft settlement', function () {
    $setup = makeSpecialEditSetup();
    $setup['settlement']->update(['status' => 'draft']);

    $this->actingAs($setup['superAdmin'])
        ->post(route('sales-settlements.update-special', $setup['settlement']), specialEditPayload($setup))
        ->assertRedirect()
        ->assertSessionHas('error', fn ($msg) => str_contains($msg, 'posted'));

    expect($setup['settlement']->fresh()->notes)->toBe('Original notes');
});

it('rejects the correction when no open accounting period covers the new date', function () {
    $setup = makeSpecialEditSetup();

    $this->mock(SalesSettlementRevertService::class, function ($mock) {
        $mock->shouldNotReceive('revert');
    });

    $this->actingAs($setup['superAdmin'])
        ->post(route('sales-settlements.update-special', $setup['settlement']), specialEditPayload($setup, [
            'settlement_date' => '2099-01-01',
        ]))
        ->assertRedirect()
        ->assertSessionHas('error', fn ($msg) => str_contains($msg, 'No open accounting period'));

    expect($setup['settlement']->fresh()->notes)->toBe('Original notes');
});

// ──────────────────────────────────────────────────────
// Revert → update → re-post orchestration
// ──────────────────────────────────────────────────────

it('reverts, applies the corrected fields, and re-posts atomically on success', function () {
    $setup = makeSpecialEditSetup();

    $this->mock(SalesSettlementRevertService::class, function ($mock) use ($setup) {
        $mock->shouldReceive('revert')
            ->once()
            ->with(Mockery::on(fn ($settlement) => $settlement->id === $setup['settlement']->id))
            ->andReturn(['success' => true, 'message' => 'Reverted']);
    });

    $this->mock(DistributionService::class, function ($mock) use ($setup) {
        $mock->shouldReceive('postSalesSettlement')
            ->once()
            ->with(Mockery::on(fn ($settlement) => $settlement->id === $setup['settlement']->id))
            ->andReturn(['success' => true, 'message' => 'Posted', 'data' => $setup['settlement']]);
    });

    $this->actingAs($setup['superAdmin'])
        ->post(route('sales-settlements.update-special', $setup['settlement']), specialEditPayload($setup, [
            'notes' => 'Corrected via special edit',
        ]))
        ->assertRedirect(route('sales-settlements.show', $setup['settlement']))
        ->assertSessionHas('success');

    expect($setup['settlement']->fresh()->notes)->toBe('Corrected via special edit');
});

it('rolls back the whole correction when revert fails, leaving the settlement untouched', function () {
    $setup = makeSpecialEditSetup();

    $this->mock(SalesSettlementRevertService::class, function ($mock) {
        $mock->shouldReceive('revert')
            ->once()
            ->andReturn(['success' => false, 'message' => 'Cannot revert: cheque already cleared.']);
    });

    $this->mock(DistributionService::class, function ($mock) {
        $mock->shouldNotReceive('postSalesSettlement');
    });

    $this->actingAs($setup['superAdmin'])
        ->post(route('sales-settlements.update-special', $setup['settlement']), specialEditPayload($setup, [
            'notes' => 'Should never be saved',
        ]))
        ->assertRedirect()
        ->assertSessionHas('error', 'Cannot revert: cheque already cleared.');

    $fresh = $setup['settlement']->fresh();
    expect($fresh->status)->toBe('posted')
        ->and($fresh->notes)->toBe('Original notes');
});

it('rolls back the whole correction when re-posting fails, leaving the settlement untouched', function () {
    $setup = makeSpecialEditSetup();

    $this->mock(SalesSettlementRevertService::class, function ($mock) {
        $mock->shouldReceive('revert')
            ->once()
            ->andReturn(['success' => true, 'message' => 'Reverted']);
    });

    $this->mock(DistributionService::class, function ($mock) {
        $mock->shouldReceive('postSalesSettlement')
            ->once()
            ->andReturn(['success' => false, 'message' => 'Cannot post: cash shortage of 100.00.']);
    });

    $this->actingAs($setup['superAdmin'])
        ->post(route('sales-settlements.update-special', $setup['settlement']), specialEditPayload($setup, [
            'notes' => 'Should be rolled back',
        ]))
        ->assertRedirect()
        ->assertSessionHas('error', fn ($msg) => str_contains($msg, 'Cannot post: cash shortage'));

    // Even though applySettlementUpdate() ran and wrote 'Should be rolled back'
    // before the re-post failed, the outer transaction must undo it entirely —
    // the settlement is left exactly as it was, still posted with its original data.
    $fresh = $setup['settlement']->fresh();
    expect($fresh->status)->toBe('posted')
        ->and($fresh->notes)->toBe('Original notes');

    expect(SalesSettlementItem::where('sales_settlement_id', $fresh->id)->sum('total_sales_value'))
        ->toEqual(1500.0);
});

it('blocks changing to a Goods Issue that already has another settlement', function () {
    $setup = makeSpecialEditSetup();

    $otherGoodsIssue = GoodsIssue::factory()->create([
        'status' => 'issued',
        'employee_id' => $setup['employee']->id,
        'vehicle_id' => Vehicle::factory()->create()->id,
        'warehouse_id' => $setup['warehouse']->id,
    ]);

    SalesSettlement::factory()->create([
        'status' => 'draft',
        'goods_issue_id' => $otherGoodsIssue->id,
        'settlement_number' => 'SETTLE-TEST-9001',
    ]);

    $this->mock(SalesSettlementRevertService::class, function ($mock) {
        $mock->shouldReceive('revert')->once()->andReturn(['success' => true, 'message' => 'Reverted']);
    });

    $this->mock(DistributionService::class, function ($mock) {
        $mock->shouldNotReceive('postSalesSettlement');
    });

    $this->actingAs($setup['superAdmin'])
        ->post(route('sales-settlements.update-special', $setup['settlement']), specialEditPayload($setup, [
            'goods_issue_id' => $otherGoodsIssue->id,
        ]))
        ->assertRedirect()
        ->assertSessionHas('error', fn ($msg) => str_contains($msg, 'already exists for this Goods Issue'));

    expect($setup['settlement']->fresh()->notes)->toBe('Original notes');
});

// ──────────────────────────────────────────────────────
// Regression: edit grid must use the settlement's saved batch price,
// not the live stock price (which may have changed since posting)
// ──────────────────────────────────────────────────────

it('loads the saved historical batch selling price into the edit grid, not the current stock batch price', function () {
    $setup = makeSpecialEditSetup();
    $settlement = $setup['settlement'];

    $goodsIssueItem = GoodsIssueItem::factory()->create([
        'goods_issue_id' => $setup['goodsIssue']->id,
        'line_no' => 1,
        'product_id' => $setup['product']->id,
        'uom_id' => Uom::factory()->create()->id,
        'quantity_issued' => 1,
        'unit_cost' => 2696.66,
        'selling_price' => 2881.97,
        'total_value' => 2881.97,
    ]);

    // Price on the stock batch has moved since the settlement was posted.
    $stockBatch = StockBatch::factory()->create([
        'product_id' => $setup['product']->id,
        'unit_cost' => 2696.66,
        'selling_price' => 3026.07,
    ]);

    $item = SalesSettlementItem::where('sales_settlement_id', $settlement->id)->firstOrFail();
    $item->update(['goods_issue_item_id' => $goodsIssueItem->id]);

    SalesSettlementItemBatch::create([
        'sales_settlement_item_id' => $item->id,
        'stock_batch_id' => $stockBatch->id,
        'batch_code' => $stockBatch->batch_code,
        'quantity_issued' => 1,
        'quantity_sold' => 1,
        'quantity_returned' => 0,
        'quantity_shortage' => 0,
        'unit_cost' => 2696.66,
        'selling_price' => 2881.97,
        'is_promotional' => false,
    ]);

    $response = $this->actingAs($setup['superAdmin'])
        ->get(route('sales-settlements.edit-special', $settlement, absolute: false));

    $response->assertSuccessful();

    $savedBatches = null;
    if (preg_match('/const savedBatchQuantities = (\{.*?\});/', $response->getContent(), $matches)) {
        $savedBatches = json_decode($matches[1], true);
    }

    $key = $goodsIssueItem->id.'_'.$stockBatch->id;

    expect($savedBatches)->not->toBeNull()
        ->and($savedBatches)->toHaveKey($key)
        ->and((float) $savedBatches[$key]['selling_price'])->toBe(2881.97)
        ->and((float) $savedBatches[$key]['unit_cost'])->toBe(2696.66);
});

it('re-posts using the settlement\'s saved batch price even when the client submits the drifted live price', function () {
    $setup = makeSpecialEditSetup();
    $settlement = $setup['settlement'];

    $goodsIssueItem = GoodsIssueItem::factory()->create([
        'goods_issue_id' => $setup['goodsIssue']->id,
        'line_no' => 1,
        'product_id' => $setup['product']->id,
        'uom_id' => Uom::factory()->create()->id,
        'quantity_issued' => 1,
        'unit_cost' => 2696.66,
        'selling_price' => 2881.97,
        'total_value' => 2881.97,
    ]);

    $stockBatch = StockBatch::factory()->create([
        'product_id' => $setup['product']->id,
        'unit_cost' => 2696.66,
        'selling_price' => 3026.07,
    ]);

    $item = SalesSettlementItem::where('sales_settlement_id', $settlement->id)->firstOrFail();
    $item->update(['goods_issue_item_id' => $goodsIssueItem->id, 'quantity_issued' => 1, 'quantity_sold' => 1, 'total_sales_value' => 2881.97, 'total_cogs' => 2696.66]);

    SalesSettlementItemBatch::create([
        'sales_settlement_item_id' => $item->id,
        'stock_batch_id' => $stockBatch->id,
        'batch_code' => $stockBatch->batch_code,
        'quantity_issued' => 1,
        'quantity_sold' => 1,
        'quantity_returned' => 0,
        'quantity_shortage' => 0,
        'unit_cost' => 2696.66,
        'selling_price' => 2881.97,
        'is_promotional' => false,
    ]);

    $this->mock(SalesSettlementRevertService::class, function ($mock) {
        $mock->shouldReceive('revert')->once()->andReturn(['success' => true, 'message' => 'Reverted']);
    });

    $this->mock(DistributionService::class, function ($mock) {
        $mock->shouldReceive('postSalesSettlement')->once()->andReturn(['success' => true, 'message' => 'Posted']);
    });

    $this->actingAs($setup['superAdmin'])
        ->post(route('sales-settlements.update-special', $settlement), specialEditPayload($setup, [
            'items' => [[
                'product_id' => $setup['product']->id,
                'goods_issue_item_id' => $goodsIssueItem->id,
                'quantity_issued' => 1,
                'quantity_sold' => 1,
                'quantity_returned' => 0,
                'quantity_shortage' => 0,
                'unit_cost' => 2696.66,
                'selling_price' => 3026.07,
                'batches' => [[
                    'stock_batch_id' => $stockBatch->id,
                    'batch_code' => $stockBatch->batch_code,
                    'quantity_issued' => 1,
                    'quantity_sold' => 1,
                    'quantity_returned' => 0,
                    'quantity_shortage' => 0,
                    'unit_cost' => 2696.66,
                    'selling_price' => 3026.07, // drifted live price sent by the client
                    'is_promotional' => false,
                ]],
            ]],
            'denom_5000' => 0, 'denom_1000' => 2, 'denom_500' => 1, 'denom_100' => 3,
            'denom_50' => 1, 'denom_20' => 1, 'denom_10' => 1, 'denom_coins' => 1.97,
        ]))
        ->assertRedirect(route('sales-settlements.show', $settlement))
        ->assertSessionHas('success');

    $rebuiltBatch = SalesSettlementItemBatch::whereHas('salesSettlementItem', fn ($q) => $q->where('sales_settlement_id', $settlement->id))
        ->where('stock_batch_id', $stockBatch->id)
        ->firstOrFail();

    expect((float) $rebuiltBatch->selling_price)->toBe(2881.97)
        ->and((float) $rebuiltBatch->unit_cost)->toBe(2696.66)
        ->and((float) $settlement->fresh()->total_sales_amount)->toBe(2881.97);
});

// ──────────────────────────────────────────────────────
// Regression: no duplicate/ghost ledger rows after a correction
// ──────────────────────────────────────────────────────

it('purges pre-existing customer ledger, inventory ledger, and stock movement rows so the re-post leaves a single clean set (no duplicate/ghost entries)', function () {
    $setup = makeSpecialEditSetup();
    $settlement = $setup['settlement'];

    $customerAccount = CustomerEmployeeAccount::create([
        'account_number' => 'CA-'.fake()->unique()->numerify('####'),
        'customer_id' => Customer::factory()->create()->id,
        'employee_id' => $settlement->employee_id,
        'opened_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    // Rows from the *original* post — these must not survive the correction.
    CustomerEmployeeAccountTransaction::create([
        'customer_employee_account_id' => $customerAccount->id,
        'transaction_date' => now()->toDateString(),
        'transaction_type' => 'recovery',
        'reference_number' => 'REC-ORIGINAL',
        'sales_settlement_id' => $settlement->id,
        'description' => 'Original recovery',
        'debit' => 0,
        'credit' => 9913,
        'payment_method' => 'cash',
    ]);

    InventoryLedgerEntry::create([
        'date' => now()->toDateString(),
        'transaction_type' => 'sale',
        'product_id' => $setup['product']->id,
        'vehicle_id' => $setup['vehicle']->id,
        'sales_settlement_id' => $settlement->id,
        'debit_qty' => 0,
        'credit_qty' => 10,
        'unit_cost' => 100,
        'running_balance' => 0,
    ]);

    $uom = Uom::factory()->create();

    StockMovement::create([
        'movement_type' => 'sale',
        'reference_type' => SalesSettlement::class,
        'reference_id' => $settlement->id,
        'movement_date' => now()->toDateString(),
        'product_id' => $setup['product']->id,
        'vehicle_id' => $setup['vehicle']->id,
        'uom_id' => $uom->id,
        'quantity' => -10,
        'unit_cost' => 100,
        'total_value' => 1000,
        'created_by' => $setup['superAdmin']->id,
    ]);

    // The mock stands in for SalesSettlementRevertService::revert(), which
    // for real would *append* reversal rows rather than delete the
    // originals — simulate that here so the test proves the controller
    // cleans up both generations, not just the ones it can see coming in.
    $this->mock(SalesSettlementRevertService::class, function ($mock) use ($settlement, $customerAccount, $setup, $uom) {
        $mock->shouldReceive('revert')
            ->once()
            ->andReturnUsing(function () use ($settlement, $customerAccount, $setup, $uom) {
                CustomerEmployeeAccountTransaction::create([
                    'customer_employee_account_id' => $customerAccount->id,
                    'transaction_date' => now()->toDateString(),
                    'transaction_type' => 'adjustment',
                    'reference_number' => 'REV-REC-ORIGINAL',
                    'sales_settlement_id' => $settlement->id,
                    'description' => 'Reversal of original recovery',
                    'debit' => 9913,
                    'credit' => 0,
                    'payment_method' => 'cash',
                ]);

                // Tagged with sales_settlement_id, matching what the real
                // reverseInventoryLedgerEntries() now does via recordAdjustment().
                InventoryLedgerEntry::create([
                    'date' => now()->toDateString(),
                    'transaction_type' => 'adjustment',
                    'product_id' => $setup['product']->id,
                    'vehicle_id' => $setup['vehicle']->id,
                    'sales_settlement_id' => $settlement->id,
                    'debit_qty' => 10,
                    'credit_qty' => 0,
                    'unit_cost' => 100,
                    'running_balance' => 0,
                    'notes' => 'Reversal',
                ]);

                // Simulates another user's row committed concurrently (higher id,
                // different settlement). The purge must never touch it.
                InventoryLedgerEntry::create([
                    'date' => now()->toDateString(),
                    'transaction_type' => 'sale',
                    'product_id' => $setup['product']->id,
                    'vehicle_id' => $setup['vehicle']->id,
                    'sales_settlement_id' => null,
                    'debit_qty' => 0,
                    'credit_qty' => 3,
                    'unit_cost' => 100,
                    'running_balance' => 0,
                    'notes' => 'Concurrent unrelated row',
                ]);

                StockMovement::create([
                    'movement_type' => 'adjustment',
                    'reference_type' => SalesSettlement::class,
                    'reference_id' => $settlement->id,
                    'movement_date' => now()->toDateString(),
                    'product_id' => $setup['product']->id,
                    'vehicle_id' => $setup['vehicle']->id,
                    'uom_id' => $uom->id,
                    'quantity' => 10,
                    'unit_cost' => 100,
                    'total_value' => 1000,
                    'created_by' => $setup['superAdmin']->id,
                ]);

                return ['success' => true, 'message' => 'Reverted'];
            });
    });

    $this->mock(DistributionService::class, function ($mock) {
        $mock->shouldReceive('postSalesSettlement')
            ->once()
            ->andReturn(['success' => true, 'message' => 'Posted']);
    });

    $this->actingAs($setup['superAdmin'])
        ->post(route('sales-settlements.update-special', $settlement), specialEditPayload($setup))
        ->assertRedirect(route('sales-settlements.show', $settlement))
        ->assertSessionHas('success');

    expect(CustomerEmployeeAccountTransaction::where('sales_settlement_id', $settlement->id)->count())->toBe(0)
        ->and(InventoryLedgerEntry::where('sales_settlement_id', $settlement->id)->count())->toBe(0)
        ->and(InventoryLedgerEntry::where('product_id', $setup['product']->id)->where('notes', 'Reversal')->count())->toBe(0)
        ->and(InventoryLedgerEntry::where('notes', 'Concurrent unrelated row')->count())->toBe(1)
        ->and(StockMovement::where('reference_type', SalesSettlement::class)->where('reference_id', $settlement->id)->count())->toBe(0);
});
