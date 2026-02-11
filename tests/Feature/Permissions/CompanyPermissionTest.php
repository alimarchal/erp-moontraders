<?php

use App\Models\Company;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['company-list', 'company-create', 'company-edit', 'company-delete'] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies index without company-list permission', function () {
    $this->get(route('companies.index'))->assertForbidden();
});

it('allows index with company-list permission', function () {
    $this->user->givePermissionTo('company-list');
    $this->get(route('companies.index'))->assertSuccessful();
});

it('denies create without company-create permission', function () {
    $this->get(route('companies.create'))->assertForbidden();
});

it('allows create with company-create permission', function () {
    $this->user->givePermissionTo('company-create');
    $this->get(route('companies.create'))->assertSuccessful();
});

it('denies store without company-create permission', function () {
    $this->post(route('companies.store'), [])->assertForbidden();
});

it('denies show without company-list permission', function () {
    $company = Company::factory()->create(['company_name' => 'Test Company']);
    $this->get(route('companies.show', $company))->assertForbidden();
});

it('denies edit without company-edit permission', function () {
    $company = Company::factory()->create(['company_name' => 'Test Company']);
    $this->get(route('companies.edit', $company))->assertForbidden();
});

it('denies update without company-edit permission', function () {
    $company = Company::factory()->create(['company_name' => 'Test Company']);
    $this->put(route('companies.update', $company), [])->assertForbidden();
});

it('denies destroy without company-delete permission', function () {
    $company = Company::factory()->create(['company_name' => 'Test Company']);
    $this->delete(route('companies.destroy', $company))->assertForbidden();
});
