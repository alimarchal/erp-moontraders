<?php

use App\Models\Category;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['category-list', 'category-create', 'category-edit', 'category-delete'] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies index without category-list permission', function () {
    $this->get(route('categories.index'))->assertForbidden();
});

it('allows index with category-list permission', function () {
    $this->user->givePermissionTo('category-list');
    $this->get(route('categories.index'))->assertSuccessful();
});

it('denies create without category-create permission', function () {
    $this->get(route('categories.create'))->assertForbidden();
});

it('allows create with category-create permission', function () {
    $this->user->givePermissionTo('category-create');
    $this->get(route('categories.create'))->assertSuccessful();
});

it('denies store without category-create permission', function () {
    $this->post(route('categories.store'), [])->assertForbidden();
});

it('denies show without category-list permission', function () {
    $category = Category::create(['name' => 'Test Category', 'slug' => 'test-category']);
    $this->get(route('categories.show', $category))->assertForbidden();
});

it('denies edit without category-edit permission', function () {
    $category = Category::create(['name' => 'Test Category', 'slug' => 'test-category']);
    $this->get(route('categories.edit', $category))->assertForbidden();
});

it('denies update without category-edit permission', function () {
    $category = Category::create(['name' => 'Test Category', 'slug' => 'test-category']);
    $this->put(route('categories.update', $category), [])->assertForbidden();
});

it('denies destroy without category-delete permission', function () {
    $category = Category::create(['name' => 'Test Category', 'slug' => 'test-category']);
    $this->delete(route('categories.destroy', $category))->assertForbidden();
});
