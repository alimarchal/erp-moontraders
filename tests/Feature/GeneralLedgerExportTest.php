<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::create(['name' => 'report-financial-general-ledger']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('report-financial-general-ledger');
    $this->actingAs($this->user);
});

it('downloads an excel file for authenticated user with permission', function () {
    $this->get(route('reports.general-ledger.export.excel'))
        ->assertSuccessful()
        ->assertDownload('general-ledger.xlsx');
});

it('exports with date range filter', function () {
    $this->get(route('reports.general-ledger.export.excel', [
        'filter' => [
            'entry_date_from' => '2025-01-01',
            'entry_date_to' => '2025-12-31',
        ],
    ]))
        ->assertSuccessful()
        ->assertDownload('general-ledger.xlsx');
});

it('exports with status filter', function () {
    $this->get(route('reports.general-ledger.export.excel', ['filter' => ['status' => 'posted']]))
        ->assertSuccessful()
        ->assertDownload('general-ledger.xlsx');
});

it('exports with account code filter', function () {
    $this->get(route('reports.general-ledger.export.excel', ['filter' => ['account_code' => '1000']]))
        ->assertSuccessful()
        ->assertDownload('general-ledger.xlsx');
});

it('denies export for unauthenticated user', function () {
    auth()->logout();

    $this->get(route('reports.general-ledger.export.excel'))
        ->assertRedirect(route('login'));
});

it('denies export without permission', function () {
    $userWithoutPermission = User::factory()->create();
    $this->actingAs($userWithoutPermission);

    $this->get(route('reports.general-ledger.export.excel'))
        ->assertForbidden();
});
