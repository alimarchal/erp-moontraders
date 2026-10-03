<?php

use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteItem;
use App\Models\PaymentGrnAllocation;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['is_super_admin' => 'Yes']);
    $this->actingAs($this->user);
});

it('shows every item column, batch details and the totals of a draft GRN', function () {
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
        ->assertSeeInOrder(['Qty', 'UP/Case', 'Ext. Value', 'Discount', 'FMR', 'Before Tax', 'Excise', 'Sales Tax', 'Adv. IT', 'Other Chg', 'Qty Rec', 'Unit Cost', 'Sell Price', 'Total W/Tax'])
        ->assertSee('NIDO-400')
        ->assertSee('Nido 400g')
        ->assertSee('Batch: B-91')
        ->assertSee('Lot: L-4')
        ->assertSee('Exp: 01 Mar 2027')
        ->assertSee('(Rej: 2.00)', false)
        ->assertSee('Promotional')
        ->assertSee('13,570.00')
        ->assertSee('INV-778')
        ->assertSee('Two cartons torn')
        ->assertSee('Post to Inventory')
        ->assertSee('postGrnPasswordModal', false);
});

it('shows the payment summary and history of a posted GRN', function () {
    $grn = GoodsReceiptNote::factory()->create(['supplier_id' => Supplier::factory()->create()->id, 'status' => 'posted', 'grand_total' => 10000, 'posted_at' => now()]);
    $payment = SupplierPayment::create([
        'payment_number' => 'PAY-2026-0042', 'supplier_id' => $grn->supplier_id, 'payment_date' => now()->toDateString(),
        'payment_method' => 'bank_transfer', 'amount' => 4000, 'status' => 'posted', 'created_by' => $this->user->id,
    ]);
    PaymentGrnAllocation::create(['supplier_payment_id' => $payment->id, 'grn_id' => $grn->id, 'allocated_amount' => 4000]);

    $this->get(route('goods-receipt-notes.show', $grn))
        ->assertSuccessful()
        ->assertSee('PAY-2026-0042')
        ->assertSee('Bank Transfer')
        ->assertSee('4,000.00')
        ->assertSee('6,000.00')
        ->assertSee('Partial')
        ->assertSee(route('supplier-payments.show', $payment->id), false);
});
