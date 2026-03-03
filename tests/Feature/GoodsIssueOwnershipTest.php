<?php

use App\Models\GoodsIssue;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'goods-issue-list', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'goods-issue-view-all', 'guard_name' => 'web']);

    $this->regularRole = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    $this->regularRole->syncPermissions(['goods-issue-list']);

    $this->adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $this->adminRole->syncPermissions(['goods-issue-list', 'goods-issue-view-all']);
});

it('user with only list permission sees only their own goods issues on index', function () {
    $user = User::factory()->create();
    $user->assignRole($this->regularRole);

    $otherUser = User::factory()->create();

    $ownIssue = GoodsIssue::factory()->create(['issued_by' => $user->id]);
    $otherIssue = GoodsIssue::factory()->create(['issued_by' => $otherUser->id]);

    $this->actingAs($user)
        ->get(route('goods-issues.index'))
        ->assertSuccessful()
        ->assertSee($ownIssue->issue_number)
        ->assertDontSee($otherIssue->issue_number);
});

it('user with view-all permission sees all goods issues on index', function () {
    $admin = User::factory()->create();
    $admin->assignRole($this->adminRole);

    $otherUser1 = User::factory()->create();
    $otherUser2 = User::factory()->create();

    $issue1 = GoodsIssue::factory()->create(['issued_by' => $otherUser1->id]);
    $issue2 = GoodsIssue::factory()->create(['issued_by' => $otherUser2->id]);

    $this->actingAs($admin)
        ->get(route('goods-issues.index'))
        ->assertSuccessful()
        ->assertSee($issue1->issue_number)
        ->assertSee($issue2->issue_number);
});

it('super admin flag user sees all goods issues on index', function () {
    $superAdmin = User::factory()->create(['is_super_admin' => 'Yes']);

    $otherUser1 = User::factory()->create();
    $otherUser2 = User::factory()->create();

    $issue1 = GoodsIssue::factory()->create(['issued_by' => $otherUser1->id]);
    $issue2 = GoodsIssue::factory()->create(['issued_by' => $otherUser2->id]);

    $this->actingAs($superAdmin)
        ->get(route('goods-issues.index'))
        ->assertSuccessful()
        ->assertSee($issue1->issue_number)
        ->assertSee($issue2->issue_number);
});
