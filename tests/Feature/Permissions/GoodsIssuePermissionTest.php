<?php

use App\Models\GoodsIssue;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['goods-issue-list', 'goods-issue-create', 'goods-issue-edit', 'goods-issue-delete', 'goods-issue-post'] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies index without goods-issue-list permission', function () {
    $this->get(route('goods-issues.index'))->assertForbidden();
});

it('allows index with goods-issue-list permission', function () {
    $this->user->givePermissionTo('goods-issue-list');
    $this->get(route('goods-issues.index'))->assertSuccessful();
});

it('denies create without goods-issue-create permission', function () {
    $this->get(route('goods-issues.create'))->assertForbidden();
});

it('allows create with goods-issue-create permission', function () {
    $this->user->givePermissionTo('goods-issue-create');
    $this->get(route('goods-issues.create'))->assertSuccessful();
});

it('denies store without goods-issue-create permission', function () {
    $this->post(route('goods-issues.store'), [])->assertForbidden();
});

it('denies show without goods-issue-list permission', function () {
    $gi = GoodsIssue::factory()->create();
    $this->get(route('goods-issues.show', $gi))->assertForbidden();
});

it('denies edit without goods-issue-edit permission', function () {
    $gi = GoodsIssue::factory()->create();
    $this->get(route('goods-issues.edit', $gi))->assertForbidden();
});

it('denies update without goods-issue-edit permission', function () {
    $gi = GoodsIssue::factory()->create();
    $this->put(route('goods-issues.update', $gi), [])->assertForbidden();
});

it('denies destroy without goods-issue-delete permission', function () {
    $gi = GoodsIssue::factory()->create();
    $this->delete(route('goods-issues.destroy', $gi))->assertForbidden();
});

it('denies post without goods-issue-post permission', function () {
    $gi = GoodsIssue::factory()->create();
    $this->post(route('goods-issues.post', $gi))->assertForbidden();
});
