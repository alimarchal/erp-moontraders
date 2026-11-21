<?php

use App\Models\CurrentStockByBatch;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\GoodsReceiptNote;
use App\Models\GrnCorrection;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\DistributionService;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    seedGrnPostingAccounts();

    $this->user = User::factory()->create(['is_super_admin' => 'Yes']);
    $this->actingAs($this->user);

    $this->uom = Uom::factory()->create();
    $this->warehouse = Warehouse::factory()->create(['disabled' => false]);
    $this->supplier = Supplier::factory()->create(['disabled' => false]);
    $this->product = Product::factory()->create(['supplier_id' => $this->supplier->id, 'uom_id' => $this->uom->id, 'is_active' => true]);
    $this->receiptDate = now()->subDays(5)->toDateString();

    // 10 cartons entered at 10 a carton: 100 units at 100 each.
    $this->grn = GoodsReceiptNote::factory()->create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
        'receipt_date' => $this->receiptDate,
    ]);
    $this->item = $this->grn->items()->create([
        'line_no' => 1,
        'product_id' => $this->product->id,
        'stock_uom_id' => $this->uom->id,
        'purchase_uom_id' => $this->uom->id,
        'qty_in_purchase_uom' => 10,
        'uom_conversion_factor' => 10,
        'qty_in_stock_uom' => 100,
        'extended_value' => 10000,
        'quantity_received' => 100,
        'quantity_accepted' => 100,
        'unit_cost' => 100,
        'total_cost' => 10000,
    ]);

    expect(app(InventoryService::class)->postGrnToInventory($this->grn->fresh())['success'])->toBeTrue();

    $this->batchId = (int) DB::table('stock_valuation_layers')->where('grn_item_id', $this->item->id)->value('stock_batch_id');
});

function issueToVan(object $test, float $quantity): void
{
    $goodsIssue = GoodsIssue::create([
        'warehouse_id' => $test->warehouse->id,
        'vehicle_id' => Vehicle::factory()->create()->id,
        'employee_id' => Employee::factory()->create()->id,
        'supplier_id' => $test->supplier->id,
        'issued_by' => $test->user->id,
        'issue_date' => now(),
        'issue_number' => 'GI-CORR-'.$quantity,
        'status' => 'draft',
    ]);
    GoodsIssueItem::create([
        'goods_issue_id' => $goodsIssue->id,
        'product_id' => $test->product->id,
        'uom_id' => $test->uom->id,
        'quantity_issued' => $quantity,
        'unit_cost' => 100,
        'selling_price' => 150,
        'total_value' => $quantity * 150,
    ]);

    expect(app(DistributionService::class)->postGoodsIssue($goodsIssue->refresh())['success'])->toBeTrue();
}

function postCorrection(object $test, array $overrides = [], ?array $line = null)
{
    return $test->post(route('goods-receipt-notes.update-special', $test->grn), array_merge([
        'reason' => 'Carton holds 12, entered as 10',
        'password' => 'password',
        'items' => [$line ?? ['id' => $test->item->id, 'product_id' => $test->product->id, 'uom_conversion_factor' => 12]],
    ], $overrides));
}

function onHand(int $batchId): float
{
    return (float) CurrentStockByBatch::where('stock_batch_id', $batchId)->value('quantity_on_hand');
}

it('posts the quantity difference as its own receipt on the GRN date and keeps the posted receipt as it was', function () {
    issueToVan($this, 40);

    postCorrection($this)->assertRedirect(route('goods-receipt-notes.show', $this->grn))->assertSessionHas('success');

    $receipts = StockMovement::where('reference_type', GoodsReceiptNote::class)->where('reference_id', $this->grn->id)
        ->where('movement_type', 'grn')->orderBy('id')->get();

    expect($receipts->pluck('quantity')->map(fn ($quantity) => (float) $quantity)->all())->toBe([100.0, 20.0])
        ->and($receipts->last()->movement_date->toDateString())->toBe($this->receiptDate)
        ->and((float) $receipts->last()->unit_cost)->toBe(round(10000 / 120, 6))
        ->and(onHand($this->batchId))->toBe(80.0)
        ->and((float) $this->item->fresh()->quantity_accepted)->toBe(120.0)
        ->and(DB::table('stock_ledger_entries')->where('stock_movement_id', $receipts->last()->id)->first(['quantity_in', 'entry_date']))
        ->toMatchArray(['quantity_in' => '20.00', 'entry_date' => $this->receiptDate])
        ->and((float) DB::table('inventory_ledger_entries')->where('goods_receipt_note_id', $this->grn->id)->selectRaw('SUM(debit_qty - credit_qty) as q')->value('q'))->toBe(120.0);
});

it('records the old and new figures, the reason and who corrected the GRN', function () {
    postCorrection($this)->assertSessionHas('success');

    $correction = GrnCorrection::with('lines')->sole();
    $line = $correction->lines->sole();

    expect($correction->goods_receipt_note_id)->toBe($this->grn->id)
        ->and($correction->reason)->toBe('Carton holds 12, entered as 10')
        ->and($correction->corrected_by)->toBe($this->user->id)
        ->and($correction->correction_number)->toContain('GRNC')
        ->and((float) $line->old_conversion_factor)->toBe(10.0)
        ->and((float) $line->new_conversion_factor)->toBe(12.0)
        ->and((float) $line->old_quantity)->toBe(100.0)
        ->and((float) $line->new_quantity)->toBe(120.0)
        ->and((float) $line->old_unit_cost)->toBe(100.0)
        ->and($line->stock_movement_id)->not->toBeNull();

    $this->get(route('goods-receipt-notes.show', $this->grn))
        ->assertOk()
        ->assertSeeText($correction->correction_number)
        ->assertSeeText('Carton holds 12, entered as 10');
});

it('takes stock off when the factor was entered too high', function () {
    postCorrection($this, line: ['id' => $this->item->id, 'product_id' => $this->product->id, 'uom_conversion_factor' => 6])->assertSessionHas('success');

    expect(onHand($this->batchId))->toBe(60.0)
        ->and((float) DB::table('stock_ledger_entries')->where('product_id', $this->product->id)->sum('quantity_out'))->toBe(40.0);
});

it('refuses a factor that leaves less than what was already issued', function () {
    issueToVan($this, 40);

    postCorrection($this, line: ['id' => $this->item->id, 'product_id' => $this->product->id, 'uom_conversion_factor' => 3])
        ->assertSessionHas('error', fn (string $message) => str_contains($message, 'already been issued, sold or adjusted'));

    expect((float) $this->item->fresh()->quantity_accepted)->toBe(100.0)
        ->and(onHand($this->batchId))->toBe(60.0)
        ->and(GrnCorrection::count())->toBe(0);
});

it('still lets the GRN be reversed after a correction while nothing was issued', function () {
    postCorrection($this)->assertSessionHas('success');

    $inventoryService = app(InventoryService::class);
    $grn = $this->grn->fresh();

    expect($inventoryService->grnReversalRefusal($grn))->toBeNull()
        ->and($inventoryService->reverseGrnInventory($grn)['success'])->toBeTrue()
        ->and(onHand($this->batchId))->toBe(0.0);
});

it('swaps the product of a line whose batch is untouched', function () {
    $right = Product::factory()->create(['supplier_id' => $this->supplier->id, 'uom_id' => $this->uom->id, 'is_active' => true, 'is_powder' => (bool) $this->product->fresh()->is_powder]);

    postCorrection($this, line: ['id' => $this->item->id, 'product_id' => $right->id, 'uom_conversion_factor' => 10])->assertSessionHas('success');

    expect($this->item->fresh()->product_id)->toBe($right->id)
        ->and((int) DB::table('stock_batches')->where('id', $this->batchId)->value('product_id'))->toBe($right->id)
        ->and(StockMovement::where('stock_batch_id', $this->batchId)->pluck('product_id')->unique()->all())->toBe([$right->id])
        ->and(GrnCorrection::sole()->lines->sole()->old_product_id)->toBe($this->product->id);
});

it('refuses to swap the product once its batch was issued', function () {
    issueToVan($this, 10);
    $other = Product::factory()->create(['supplier_id' => $this->supplier->id, 'uom_id' => $this->uom->id, 'is_active' => true, 'is_powder' => (bool) $this->product->fresh()->is_powder]);

    postCorrection($this, line: ['id' => $this->item->id, 'product_id' => $other->id, 'uom_conversion_factor' => 10])
        ->assertSessionHas('error', fn (string $message) => str_contains($message, 'cannot be changed'));

    expect($this->item->fresh()->product_id)->toBe($this->product->id);
});

it('refuses the correction with a wrong password', function () {
    postCorrection($this, ['password' => 'not-it'])->assertSessionHas('error', 'Invalid password. A GRN Correction needs your password confirmation.');

    expect((float) $this->item->fresh()->quantity_accepted)->toBe(100.0)
        ->and(GrnCorrection::count())->toBe(0);
});

it('requires a reason', function () {
    postCorrection($this, ['reason' => ''])->assertSessionHasErrors(['reason' => 'The reason field is required.']);

    expect(GrnCorrection::count())->toBe(0);
});

it('is refused to a user who is not a super admin', function () {
    $this->actingAs(User::factory()->create(['is_super_admin' => 'No']));

    postCorrection($this)->assertForbidden();

    expect(GrnCorrection::count())->toBe(0);
});

it('stops a new GRN whose conversion factor differs from the product\'s last GRN until it is confirmed', function () {
    $payload = [
        'receipt_date' => now()->toDateString(),
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'items' => [[
            'product_id' => $this->product->id,
            'stock_uom_id' => $this->uom->id,
            'purchase_uom_id' => $this->uom->id,
            'qty_in_purchase_uom' => 5,
            'uom_conversion_factor' => 1,
            'qty_in_stock_uom' => 5,
            'quantity_received' => 5,
            'quantity_accepted' => 5,
            'unit_cost' => 100,
        ]],
    ];

    $this->post(route('goods-receipt-notes.store'), $payload)
        ->assertSessionHasErrors(['confirm_unusual_factor' => 'Check the conversion factor: line 1 is 1, the last GRN of this product used 10. If it is right, tick "The conversion factor is right" and save again.']);

    expect(GoodsReceiptNote::count())->toBe(1);

    $this->post(route('goods-receipt-notes.store'), $payload + ['confirm_unusual_factor' => '1'])->assertSessionHasNoErrors();

    expect(GoodsReceiptNote::count())->toBe(2);
});
