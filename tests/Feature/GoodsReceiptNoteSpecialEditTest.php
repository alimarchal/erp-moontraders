<?php

use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows only products belonging to the posted GRN supplier in the special edit form', function () {
    $this->actingAs(User::factory()->create(['is_super_admin' => 'Yes']));

    $supplier = Supplier::factory()->create(['supplier_name' => 'Nestle']);
    $otherSupplier = Supplier::factory()->create(['supplier_name' => 'Other Supplier']);
    $uom = Uom::factory()->create(['uom_name' => 'Piece']);
    $grnProduct = Product::factory()->create([
        'product_code' => 'NESTLE-001',
        'product_name' => 'Nestle Original',
        'supplier_id' => $supplier->id,
        'uom_id' => $uom->id,
    ]);
    $otherProduct = Product::factory()->create([
        'product_code' => 'OTHER-001',
        'product_name' => 'Other Supplier Product',
        'supplier_id' => $otherSupplier->id,
        'uom_id' => $uom->id,
    ]);
    $grn = GoodsReceiptNote::factory()->create([
        'supplier_id' => $supplier->id,
        'status' => 'posted',
    ]);

    GoodsReceiptNoteItem::factory()->create([
        'grn_id' => $grn->id,
        'product_id' => $grnProduct->id,
        'stock_uom_id' => $uom->id,
        'purchase_uom_id' => $uom->id,
        'quantity_accepted' => 1,
        'qty_in_stock_uom' => 1,
    ]);

    $response = $this->get(route('goods-receipt-notes.edit-special', $grn));

    $response->assertSuccessful()
        ->assertSee('product-select')
        ->assertSee($grnProduct->product_code)
        ->assertDontSee($grnProduct->product_name)
        ->assertDontSee($otherProduct->product_name);
});
