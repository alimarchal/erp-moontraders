<?php

use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\GoodsReceiptNote;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Notifications\StockLedgerOutOfStep;
use App\Services\AccountingService;
use App\Services\BatchTransferService;
use App\Services\DistributionService;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    seedGrnPostingAccounts();

    $this->user = User::factory()->create(['is_super_admin' => 'Yes']);
    $this->actingAs($this->user);

    $this->uom = Uom::factory()->create();
    $this->warehouse = Warehouse::factory()->create(['disabled' => false]);
    $this->supplier = Supplier::factory()->create(['disabled' => false]);
    $this->product = Product::factory()->create(['supplier_id' => $this->supplier->id, 'uom_id' => $this->uom->id, 'is_active' => true]);

    // 10 cartons booked at 10 a carton: 100 units at 100 each.
    $this->grn = GoodsReceiptNote::factory()->create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
        'receipt_date' => now()->toDateString(),
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

    $goodsIssue = GoodsIssue::create([
        'warehouse_id' => $this->warehouse->id,
        'vehicle_id' => Vehicle::factory()->create()->id,
        'employee_id' => Employee::factory()->create()->id,
        'supplier_id' => $this->supplier->id,
        'issued_by' => $this->user->id,
        'issue_date' => now(),
        'issue_number' => 'GI-STEP-1',
        'status' => 'draft',
    ]);
    GoodsIssueItem::create([
        'goods_issue_id' => $goodsIssue->id,
        'product_id' => $this->product->id,
        'uom_id' => $this->uom->id,
        'quantity_issued' => 40,
        'unit_cost' => 100,
        'selling_price' => 150,
        'total_value' => 6000,
    ]);

    expect(app(DistributionService::class)->postGoodsIssue($goodsIssue->refresh())['success'])->toBeTrue();
});

function stockLedgerBalance(string $code): float
{
    return round((float) DB::table('journal_entry_details as line')
        ->join('journal_entries as je', 'je.id', '=', 'line.journal_entry_id')
        ->where('je.status', 'posted')
        ->where('line.chart_of_account_id', ChartOfAccount::where('account_code', $code)->value('id'))
        ->selectRaw('COALESCE(SUM(line.debit - line.credit), 0) as balance')
        ->value('balance'), 2);
}

function warehouseStockValue(): float
{
    return round((float) DB::table('current_stock_by_batch')->selectRaw('COALESCE(SUM(quantity_on_hand * unit_cost), 0) as v')->value('v'), 2);
}

function vanStockValue(): float
{
    return round((float) DB::table('van_stock_batches')->selectRaw('COALESCE(SUM(quantity_on_hand * unit_cost), 0) as v')->value('v'), 2);
}

function correctConversionFactor(object $test, float $factor)
{
    return $test->post(route('goods-receipt-notes.update-special', $test->grn), [
        'items' => [['id' => $test->item->id, 'product_id' => $test->product->id, 'uom_conversion_factor' => $factor]],
    ]);
}

it('starts with the ledger equal to the stock', function () {
    expect(stockLedgerBalance('1151'))->toBe(warehouseStockValue())
        ->and(stockLedgerBalance('1155'))->toBe(vanStockValue())
        ->and(vanStockValue())->toBe(4000.0);

    $this->artisan('accounting:reconcile-stock-gl')->assertSuccessful();
});

it('keeps the ledger equal to the stock when a GRN is corrected after stock was issued', function () {
    // The 10 cartons held 12 each: 120 units at 83.33, 40 of them already on the van.
    correctConversionFactor($this, 12)->assertSessionHas('success');

    expect(warehouseStockValue())->toBe(round(80 * 83.333333, 2))
        ->and(stockLedgerBalance('1151'))->toBe(warehouseStockValue())
        ->and(stockLedgerBalance('1155'))->toBe(vanStockValue())
        ->and(vanStockValue())->toBe(round(40 * 83.33, 2));

    $adjustment = JournalEntry::where('reference', 'like', "RECOST-{$this->grn->grn_number}-%")->sole();
    expect((float) $adjustment->details->sum('debit'))->toBe((float) $adjustment->details->sum('credit'));

    $this->artisan('accounting:reconcile-stock-gl', ['--tolerance' => 1])->assertSuccessful();
});

it('rolls the correction back when its adjusting entry cannot be posted', function () {
    app()->instance(AccountingService::class, new class extends AccountingService
    {
        public function __construct() {}

        public function createJournalEntry(array $data): array
        {
            return ['success' => false, 'data' => null, 'message' => 'Period closed'];
        }
    });

    correctConversionFactor($this, 12)->assertSessionHas('error');

    expect((float) $this->item->fresh()->quantity_accepted)->toBe(100.0)
        ->and(warehouseStockValue())->toBe(6000.0);
});

it('refuses to re-cost a batch that was partly transferred to another product', function () {
    $other = Product::factory()->create(['supplier_id' => $this->supplier->id, 'uom_id' => $this->uom->id, 'is_active' => true]);
    $batchId = (int) DB::table('stock_movements')->where('reference_type', GoodsReceiptNote::class)->where('reference_id', $this->grn->id)->value('stock_batch_id');

    expect(app(BatchTransferService::class)->transfer($batchId, $other->id, 10, 'Mislabelled')['success'])->toBeTrue();

    correctConversionFactor($this, 12)->assertSessionHas('error', fn (string $message) => str_contains($message, 'partly transferred'));

    expect((float) $this->item->fresh()->quantity_accepted)->toBe(100.0)
        ->and(stockLedgerBalance('1151'))->toBe(warehouseStockValue());
});

it('keeps the ledger equal to the stock across a batch transfer', function () {
    $other = Product::factory()->create(['supplier_id' => $this->supplier->id, 'uom_id' => $this->uom->id, 'is_active' => true]);
    $batchId = (int) DB::table('stock_movements')->where('reference_type', GoodsReceiptNote::class)->where('reference_id', $this->grn->id)->value('stock_batch_id');

    app(BatchTransferService::class)->transfer($batchId, $other->id, 25, 'Mislabelled');

    expect(stockLedgerBalance('1151'))->toBe(warehouseStockValue());
});

describe('when the ledger has drifted from the stock', function () {
    beforeEach(function () {
        // A document that credited Stock In Hand 250 more than the stock it moved.
        app(AccountingService::class)->createJournalEntry([
            'entry_date' => now()->toDateString(),
            'reference' => 'DRIFT-1',
            'description' => 'Drift',
            'lines' => [
                ['account_id' => ChartOfAccount::where('account_code', '5111')->value('id'), 'debit' => 250, 'credit' => 0, 'cost_center_id' => CostCenter::where('code', 'CC006')->value('id')],
                ['account_id' => ChartOfAccount::where('account_code', '1151')->value('id'), 'debit' => 0, 'credit' => 250, 'cost_center_id' => CostCenter::where('code', 'CC006')->value('id')],
            ],
            'auto_post' => true,
        ]);
    });

    it('reports the gap and emails the backup recipient', function () {
        Notification::fake();
        config(['backup.notifications.mail.to' => 'owner@example.com']);

        $this->artisan('accounting:reconcile-stock-gl', ['--tolerance' => 100])->assertFailed();

        Notification::assertSentOnDemand(StockLedgerOutOfStep::class);
    });

    it('stays quiet about a gap inside the tolerance', function () {
        $this->artisan('accounting:reconcile-stock-gl', ['--tolerance' => 500])->assertSuccessful();
    });

    it('changes nothing on a dry run of the true-up', function () {
        $this->artisan('accounting:reconcile-stock-gl', ['--post' => true, '--dry-run' => true])->assertSuccessful();

        expect(stockLedgerBalance('1151'))->toBe(warehouseStockValue() - 250);
    });

    it('brings the ledger back to the stock value against cost of goods sold', function () {
        $this->artisan('accounting:reconcile-stock-gl', ['--post' => true])->assertSuccessful();

        expect(stockLedgerBalance('1151'))->toBe(warehouseStockValue())
            ->and(stockLedgerBalance('5111'))->toBe(0.0);
    });

    it('refuses to plug a gap larger than --max', function () {
        $this->artisan('accounting:reconcile-stock-gl', ['--post' => true, '--max' => 100])->assertFailed();

        expect(stockLedgerBalance('1151'))->toBe(warehouseStockValue() - 250);
    });
});
