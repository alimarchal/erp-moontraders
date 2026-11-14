<?php

use App\Models\ChartOfAccount;
use App\Models\CurrentStockByBatch;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\VanStockBatch;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\AccountingService;
use App\Services\DistributionService;
use App\Services\GrnCostDriftService;
use App\Services\InventoryGlAdjustmentService;
use App\Services\InventoryService;

/**
 * An "FMR allowed" GRN as the form used to post it: 100 pieces, 10,000 before FMR, 300 FMR, so the line was costed
 * at 9,700 (97 a piece) while the journal also credited the 300 FMR as income and plugged it on Round Off.
 *
 * @return array{grn: GoodsReceiptNote, item: GoodsReceiptNoteItem, batch: StockBatch, warehouse: Warehouse, product: Product, uom: Uom}
 */
function postGrnCostedNetOfFmr(float $roundOff = 300.0): array
{
    seedGrnPostingAccounts();

    $supplier = Supplier::factory()->create(['is_fmr_allowed' => true]);
    $warehouse = Warehouse::factory()->create();
    $uom = Uom::factory()->create();
    $product = Product::factory()->create(['supplier_id' => $supplier->id]);

    $grn = GoodsReceiptNote::factory()->create([
        'supplier_id' => $supplier->id, 'warehouse_id' => $warehouse->id, 'status' => 'draft', 'receipt_date' => now(),
    ]);
    $item = $grn->items()->create([
        'line_no' => 1, 'product_id' => $product->id, 'stock_uom_id' => $uom->id, 'purchase_uom_id' => $uom->id,
        'qty_in_purchase_uom' => 100, 'uom_conversion_factor' => 1, 'qty_in_stock_uom' => 100,
        'quantity_received' => 100, 'quantity_accepted' => 100,
        'extended_value' => 10000, 'discount_value' => 0, 'fmr_allowance' => 300, 'sales_tax_value' => 0,
        'discounted_value_before_tax' => 9700, 'total_value_with_taxes' => 9700,
        'unit_cost' => 97, 'total_cost' => 9700, 'selling_price' => 120,
    ]);

    $legacyPosting = new class($roundOff) extends InventoryService
    {
        public function __construct(private float $roundOff) {}

        protected function createGrnJournalEntry(GoodsReceiptNote $grn)
        {
            $account = fn (string $code) => ChartOfAccount::where('account_code', $code)->value('id');
            $line = fn (string $code, float $debit, float $credit) => ['account_id' => $account($code), 'debit' => $debit, 'credit' => $credit, 'description' => $code, 'cost_center_id' => null];

            return app(AccountingService::class)->createJournalEntry([
                'entry_date' => now()->toDateString(),
                'reference' => $grn->grn_number,
                'description' => 'GRN posted the old way',
                'lines' => [
                    $line('1151', 10000 - $this->roundOff, 0),
                    $line('5271', $this->roundOff, 0),
                    $line('4210', 0, 300),
                    $line('2142', 0, 9700),
                ],
                'auto_post' => true,
            ])['data'];
        }
    };

    expect($legacyPosting->postGrnToInventory($grn->fresh())['success'])->toBeTrue();

    return [
        'grn' => $grn->fresh(), 'item' => $item, 'warehouse' => $warehouse, 'product' => $product, 'uom' => $uom,
        'batch' => StockBatch::where('product_id', $product->id)->firstOrFail(),
    ];
}

function roundOffBalance(): float
{
    return (float) DB::table('journal_entry_details as line')
        ->join('journal_entries as entry', 'entry.id', '=', 'line.journal_entry_id')
        ->join('chart_of_accounts as account', 'account.id', '=', 'line.chart_of_account_id')
        ->where('entry.status', 'posted')
        ->where('account.account_code', '5271')
        ->sum(DB::raw('line.debit - line.credit'));
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('restates a GRN costed net of FMR at its cost before FMR and moves the Round Off to the stock', function () {
    $fx = postGrnCostedNetOfFmr();

    // 40 of the 100 pieces went onto a van at the old 97.
    $issue = GoodsIssue::create([
        'warehouse_id' => $fx['warehouse']->id, 'vehicle_id' => Vehicle::factory()->create()->id, 'employee_id' => Employee::factory()->create()->id,
        'issued_by' => auth()->id(), 'issue_date' => now(), 'issue_number' => 'GI-FMR-1', 'status' => 'draft',
    ]);
    GoodsIssueItem::create([
        'goods_issue_id' => $issue->id, 'product_id' => $fx['product']->id, 'uom_id' => $fx['uom']->id,
        'quantity_issued' => 40, 'unit_cost' => 97, 'selling_price' => 120, 'total_cogs' => 3880, 'total_value' => 4800,
    ]);
    expect(app(DistributionService::class)->postGoodsIssue($issue->refresh())['success'])->toBeTrue();

    $service = app(GrnCostDriftService::class);
    $gapsBefore = app(InventoryGlAdjustmentService::class)->stockLedgerGaps();

    expect($service->fmrNettedGrnIds()->all())->toBe([$fx['grn']->id]);

    $result = DB::transaction(fn () => $service->repairFmrNettedCost($fx['grn']->id));

    expect($result['note'])->toBeNull()
        ->and($result['lines'])->toBe(1)
        ->and($result['round_off'])->toBe(300.0);

    // The line, its batch and the stock carry the cost before FMR: (9,700 + 300) / 100.
    expect((float) $fx['item']->fresh()->unit_cost)->toBe(100.0)
        ->and((float) $fx['item']->fresh()->total_cost)->toBe(10000.0)
        ->and((float) $fx['batch']->fresh()->unit_cost)->toBe(100.0)
        ->and((float) CurrentStockByBatch::where('stock_batch_id', $fx['batch']->id)->value('unit_cost'))->toBe(100.0)
        ->and((float) VanStockBatch::where('product_id', $fx['product']->id)->value('unit_cost'))->toBe(100.0);

    // Round Off is cleared; the 300 now sits on the warehouse (60 × 3) and the van (40 × 3).
    $fix = JournalEntry::with('details.account')->find($result['journal_entry_id']);
    $byAccount = $fix->details->mapWithKeys(fn ($line) => [$line->account->account_code => round((float) $line->debit - (float) $line->credit, 2)])->all();

    expect(round(roundOffBalance(), 2))->toBe(0.0)
        ->and($byAccount)->toMatchArray(['1151' => 180.0, '1155' => 120.0, '5271' => -300.0])
        ->and(collect(app(InventoryGlAdjustmentService::class)->stockLedgerGaps())->pluck('gap')->all())->toBe(collect($gapsBefore)->pluck('gap')->all())
        ->and((float) app(InventoryGlAdjustmentService::class)->stockLedgerGaps()['1151']['stock'])->toBe(6000.0);

    // Running it again changes nothing.
    expect($service->fmrNettedGrnIds()->all())->toBe([])
        ->and($service->repairFmrNettedCost($fx['grn']->id)['note'])->toBe('No line is costed net of FMR.');
});

it('leaves a GRN alone when its Round Off does not hold the FMR', function () {
    $fx = postGrnCostedNetOfFmr(roundOff: 250.0);

    $result = DB::transaction(fn () => app(GrnCostDriftService::class)->repairFmrNettedCost($fx['grn']->id));

    expect($result['note'])->toContain('left for review')
        ->and((float) $fx['item']->fresh()->unit_cost)->toBe(97.0)
        ->and((float) $fx['batch']->fresh()->unit_cost)->toBe(97.0);
});
