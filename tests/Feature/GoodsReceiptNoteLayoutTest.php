<?php

use App\Models\AppSetting;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_super_admin' => 'Yes']));
});

it('keeps the classic GRN form when no layout is chosen', function () {
    $this->get(route('goods-receipt-notes.create'))
        ->assertSuccessful()
        ->assertSee('Line Items')
        ->assertSee('x-data="grnForm(', false)
        ->assertDontSee('grnLayout(grnForm(', false);
});

it('shows the spreadsheet grid on create and edit when Option A is chosen', function () {
    AppSetting::put('layout.goods_receipt_notes', 'a');
    $grn = GoodsReceiptNote::factory()->create(['supplier_id' => Supplier::factory()->create()->id, 'status' => 'draft']);

    $this->get(route('goods-receipt-notes.create'))
        ->assertSuccessful()
        ->assertSee('grnLayout(grnForm(', false)
        ->assertSee('Columns to show')
        ->assertSee('Taxes &amp; charges', false)
        ->assertSee('Create GRN')
        ->assertDontSee('Line Items');

    $this->get(route('goods-receipt-notes.edit', $grn))
        ->assertSuccessful()
        ->assertSee('Columns to show')
        ->assertSee('Save changes');
});

it('shows the lines list and line editor on create and edit when Option B is chosen', function () {
    AppSetting::put('layout.goods_receipt_notes', 'b');
    $grn = GoodsReceiptNote::factory()->create(['supplier_id' => Supplier::factory()->create()->id, 'status' => 'draft', 'notes' => 'Two cartons torn']);

    $this->get(route('goods-receipt-notes.create'))
        ->assertSuccessful()
        ->assertSee('grnLayout(grnForm(', false)
        ->assertSee("'Save & next line'", false)
        ->assertSee('Batch &amp; expiry', false)
        ->assertSee('items[${index}][expiry_date]', false)
        ->assertSee('Create GRN');

    $this->get(route('goods-receipt-notes.edit', $grn))
        ->assertSuccessful()
        ->assertSee('Lines')
        ->assertSee('Two cartons torn')
        ->assertSee('Save changes');
});

it('shows the redesigned view with every section and action in each new layout', function (string $layout) {
    AppSetting::put('layout.goods_receipt_notes', $layout);
    $grn = GoodsReceiptNote::factory()->create(['supplier_id' => Supplier::factory()->create()->id, 'status' => 'draft', 'supplier_invoice_number' => 'INV-778', 'notes' => 'Two cartons torn']);
    GoodsReceiptNoteItem::factory()->create([
        'grn_id' => $grn->id, 'line_no' => 1,
        'product_id' => Product::factory()->create(['product_code' => 'NIDO-400', 'product_name' => 'Nido 400g'])->id,
        'qty_in_purchase_uom' => 10, 'unit_price_per_case' => 1200, 'extended_value' => 12000, 'discount_value' => 500,
        'discounted_value_before_tax' => 11500, 'sales_tax_value' => 2070, 'total_value_with_taxes' => 13570,
        'quantity_received' => 240, 'quantity_rejected' => 2, 'unit_cost' => 50, 'total_cost' => 12000, 'selling_price' => 60,
        'batch_number' => 'B-91', 'lot_number' => 'L-4', 'expiry_date' => '2027-03-01', 'is_promotional' => true,
    ]);

    $this->get(route('goods-receipt-notes.show', $grn))
        ->assertSuccessful()
        ->assertSee('Nido 400g')
        ->assertSee('NIDO-400')
        ->assertSee('B-91')
        ->assertSee('01 Mar 2027')
        ->assertSee('13,570.00')
        ->assertSee('INV-778')
        ->assertSee('Two cartons torn')
        ->assertSee('Supplier ledger entry')
        ->assertSee('Timeline')
        ->assertSee('Post to Inventory')
        ->assertSee('postGrnPasswordModal', false)
        ->assertDontSee('ak-kpis', false);
})->with(['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i']);

it('shows the entry bar on create and edit when Option C is chosen', function () {
    AppSetting::put('layout.goods_receipt_notes', 'c');
    $grn = GoodsReceiptNote::factory()->create(['supplier_id' => Supplier::factory()->create()->id, 'status' => 'draft']);

    $this->get(route('goods-receipt-notes.create'))
        ->assertSuccessful()
        ->assertSee('id="grn-entry-bar"', false)
        ->assertSee('Added lines')
        ->assertSee("'Add to list'", false)
        ->assertSee('items[${index}][batch_number]', false)
        ->assertSee('Create GRN');

    $this->get(route('goods-receipt-notes.edit', $grn))
        ->assertSuccessful()
        ->assertSee('id="grn-entry-bar"', false)
        ->assertSee('Save changes');
});

it('shows one card per line on create and edit when Option D is chosen', function () {
    AppSetting::put('layout.goods_receipt_notes', 'd');
    $grn = GoodsReceiptNote::factory()->create(['supplier_id' => Supplier::factory()->create()->id, 'status' => 'draft']);

    $this->get(route('goods-receipt-notes.create'))
        ->assertSuccessful()
        ->assertSee('<article', false)
        ->assertSee('Remove line')
        ->assertSee('items[${index}][expiry_date]', false)
        ->assertSee('Create GRN');

    $this->get(route('goods-receipt-notes.edit', $grn))
        ->assertSuccessful()
        ->assertSee('<article', false)
        ->assertSee('Save changes');
});

it('opens create and edit in the step-by-step, invoice and focus layouts', function (string $layout, string $marker) {
    AppSetting::put('layout.goods_receipt_notes', $layout);
    $grn = GoodsReceiptNote::factory()->create(['supplier_id' => Supplier::factory()->create()->id, 'status' => 'draft']);

    $this->get(route('goods-receipt-notes.create'))
        ->assertSuccessful()
        ->assertSee($marker)
        ->assertSee('Batch &amp; expiry', false)
        ->assertSee('items[${index}][batch_number]', false)
        ->assertSee('Create GRN');

    $this->get(route('goods-receipt-notes.edit', $grn))
        ->assertSuccessful()
        ->assertSee($marker)
        ->assertSee('Save changes');
})->with([
    'step by step' => ['e', 'Review before saving'],
    'invoice' => ['f', 'Amount due to supplier'],
    'focus' => ['g', 'Done, start entering lines'],
    'enterprise' => ['h', 'General information'],
    'lines + popup' => ['i', 'Click a line to open it.'],
]);

it('shows the GRN list in the goods issues style with status tabs, filters and totals', function () {
    $supplier = Supplier::factory()->create(['supplier_name' => 'Layout Supplier']);
    GoodsReceiptNote::factory()->create(['supplier_id' => $supplier->id, 'status' => 'draft', 'grn_number' => 'GRN-LAYOUT-1', 'grand_total' => 1500]);

    $this->get(route('goods-receipt-notes.index'))
        ->assertSuccessful()
        ->assertSee('GRN-LAYOUT-1')
        ->assertSee('Layout Supplier')
        ->assertSee('Draft')
        ->assertSee('Posted')
        ->assertSee('Reversed')
        ->assertSee('1,500');

    $this->get(route('goods-receipt-notes.index', ['filter' => ['status' => 'posted']]))
        ->assertSuccessful()
        ->assertDontSee('GRN-LAYOUT-1');

    $this->get(route('goods-receipt-notes.index', ['filter' => ['search' => 'layout-1', 'status' => 'all']]))
        ->assertSuccessful()
        ->assertSee('GRN-LAYOUT-1');
});
