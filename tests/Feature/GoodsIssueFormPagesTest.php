<?php

use App\Models\GoodsIssue;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['goods-issue-list', 'goods-issue-create', 'goods-issue-edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['goods-issue-list', 'goods-issue-create', 'goods-issue-edit']);
    $this->actingAs($this->user);
});

it('shows the create form in three sections with every field the form posts', function () {
    $this->get(route('goods-issues.create'))
        ->assertSuccessful()
        ->assertSeeInOrder(['Issue details', 'Products to issue', 'Notes'])
        ->assertSee('id="goodsIssueForm"', false)
        ->assertSee('name="supplier_ids[]"', false)
        ->assertSee('name="issue_date"', false)
        ->assertSee('name="warehouse_id"', false)
        ->assertSee('name="employee_id"', false)
        ->assertSee('name="vehicle_id"', false)
        ->assertSee('name="notes"', false)
        ->assertSee('validateAndSubmit()', false)
        ->assertSee('Create Goods Issue');
});

it('shows the edit form of a draft with its number and the same fields', function () {
    $draft = GoodsIssue::factory()->create(['status' => 'draft', 'issued_by' => $this->user->id, 'notes' => 'Morning load']);

    $this->get(route('goods-issues.edit', $draft))
        ->assertSuccessful()
        ->assertSee('Edit Goods Issue '.$draft->issue_number)
        ->assertSeeInOrder(['Issue details', 'Products to issue', 'Notes'])
        ->assertSee('name="_method" value="PUT"', false)
        ->assertSee('Morning load')
        ->assertSee('Update Goods Issue');
});
