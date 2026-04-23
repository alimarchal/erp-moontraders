<?php

use App\Models\GoodsReceiptNote;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'goods-receipt-note-list', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'goods-receipt-note-view-all', 'guard_name' => 'web']);

    $this->regularRole = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    $this->regularRole->syncPermissions(['goods-receipt-note-list']);

    $this->adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $this->adminRole->syncPermissions(['goods-receipt-note-list', 'goods-receipt-note-view-all']);
});

it('user with only list permission sees only their own GRNs on index', function () {
    $user = User::factory()->create();
    $user->assignRole($this->regularRole);

    $otherUser = User::factory()->create();

    $ownGrn = GoodsReceiptNote::factory()->create(['supplier_id' => Supplier::factory()->create()->id, 'received_by' => $user->id]);
    $otherGrn = GoodsReceiptNote::factory()->create(['supplier_id' => Supplier::factory()->create()->id, 'received_by' => $otherUser->id]);

    $this->actingAs($user)
        ->get(route('goods-receipt-notes.index'))
        ->assertSuccessful()
        ->assertSee($ownGrn->grn_number)
        ->assertDontSee($otherGrn->grn_number);
});

it('user with view-all permission sees all GRNs on index', function () {
    $admin = User::factory()->create();
    $admin->assignRole($this->adminRole);

    $otherUser1 = User::factory()->create();
    $otherUser2 = User::factory()->create();

    $grn1 = GoodsReceiptNote::factory()->create(['supplier_id' => Supplier::factory()->create()->id, 'received_by' => $otherUser1->id]);
    $grn2 = GoodsReceiptNote::factory()->create(['supplier_id' => Supplier::factory()->create()->id, 'received_by' => $otherUser2->id]);

    $this->actingAs($admin)
        ->get(route('goods-receipt-notes.index'))
        ->assertSuccessful()
        ->assertSee($grn1->grn_number)
        ->assertSee($grn2->grn_number);
});

it('super admin flag user sees all GRNs on index', function () {
    $superAdmin = User::factory()->create(['is_super_admin' => 'Yes']);

    $otherUser1 = User::factory()->create();
    $otherUser2 = User::factory()->create();

    $grn1 = GoodsReceiptNote::factory()->create(['supplier_id' => Supplier::factory()->create()->id, 'received_by' => $otherUser1->id]);
    $grn2 = GoodsReceiptNote::factory()->create(['supplier_id' => Supplier::factory()->create()->id, 'received_by' => $otherUser2->id]);

    $this->actingAs($superAdmin)
        ->get(route('goods-receipt-notes.index'))
        ->assertSuccessful()
        ->assertSee($grn1->grn_number)
        ->assertSee($grn2->grn_number);
});

it('shows previous-date drafts by default while keeping non-drafts limited to today', function () {
    $user = User::factory()->create();
    $user->assignRole($this->regularRole);

    $supplierId = Supplier::factory()->create()->id;

    $oldDraft = GoodsReceiptNote::factory()->create([
        'supplier_id' => $supplierId,
        'received_by' => $user->id,
        'status' => 'draft',
        'receipt_date' => now()->subDays(5),
    ]);

    $oldPosted = GoodsReceiptNote::factory()->create([
        'supplier_id' => $supplierId,
        'received_by' => $user->id,
        'status' => 'posted',
        'receipt_date' => now()->subDays(5),
    ]);

    $todayPosted = GoodsReceiptNote::factory()->create([
        'supplier_id' => $supplierId,
        'received_by' => $user->id,
        'status' => 'posted',
        'receipt_date' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('goods-receipt-notes.index'))
        ->assertSuccessful()
        ->assertSee($oldDraft->grn_number)
        ->assertSee($todayPosted->grn_number)
        ->assertDontSee($oldPosted->grn_number);
});
