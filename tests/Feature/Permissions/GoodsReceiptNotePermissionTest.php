<?php

use App\Models\GoodsReceiptNote;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['goods-receipt-note-list', 'goods-receipt-note-create', 'goods-receipt-note-edit', 'goods-receipt-note-delete', 'goods-receipt-note-post', 'goods-receipt-note-reverse'] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies index without goods-receipt-note-list permission', function () {
    $this->get(route('goods-receipt-notes.index'))->assertForbidden();
});

it('allows index with goods-receipt-note-list permission', function () {
    $this->user->givePermissionTo('goods-receipt-note-list');
    $this->get(route('goods-receipt-notes.index'))->assertSuccessful();
});

it('denies create without goods-receipt-note-create permission', function () {
    $this->get(route('goods-receipt-notes.create'))->assertForbidden();
});

it('allows create with goods-receipt-note-create permission', function () {
    $this->user->givePermissionTo('goods-receipt-note-create');
    $this->get(route('goods-receipt-notes.create'))->assertSuccessful();
});

it('denies store without goods-receipt-note-create permission', function () {
    $this->post(route('goods-receipt-notes.store'), [])->assertForbidden();
});

it('denies show without goods-receipt-note-list permission', function () {
    $grn = GoodsReceiptNote::factory()->create(['supplier_id' => Supplier::factory()->create()->id]);
    $this->get(route('goods-receipt-notes.show', $grn))->assertForbidden();
});

it('denies edit without goods-receipt-note-edit permission', function () {
    $grn = GoodsReceiptNote::factory()->create(['supplier_id' => Supplier::factory()->create()->id]);
    $this->get(route('goods-receipt-notes.edit', $grn))->assertForbidden();
});

it('denies update without goods-receipt-note-edit permission', function () {
    $grn = GoodsReceiptNote::factory()->create(['supplier_id' => Supplier::factory()->create()->id]);
    $this->put(route('goods-receipt-notes.update', $grn), [])->assertForbidden();
});

it('denies destroy without goods-receipt-note-delete permission', function () {
    $grn = GoodsReceiptNote::factory()->create(['supplier_id' => Supplier::factory()->create()->id]);
    $this->delete(route('goods-receipt-notes.destroy', $grn))->assertForbidden();
});

it('denies post without goods-receipt-note-post permission', function () {
    $grn = GoodsReceiptNote::factory()->create(['supplier_id' => Supplier::factory()->create()->id]);
    $this->post(route('goods-receipt-notes.post', $grn))->assertForbidden();
});

it('denies reverse without goods-receipt-note-reverse permission', function () {
    $grn = GoodsReceiptNote::factory()->create(['supplier_id' => Supplier::factory()->create()->id]);
    $this->post(route('goods-receipt-notes.reverse', $grn))->assertForbidden();
});
