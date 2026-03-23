<?php

use App\Models\InvoiceSummary;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::create(['name' => 'report-audit-invoice-summary']);
    Permission::create(['name' => 'report-audit-invoice-summary-manage']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('report-audit-invoice-summary');
    $this->user->givePermissionTo('report-audit-invoice-summary-manage');

    $this->supplier = Supplier::factory()->create([
        'supplier_name' => 'Nestlé Pakistan',
        'short_name' => 'Nestle',
        'disabled' => false,
    ]);
});

test('invoice summary page loads for authenticated user', function () {
    $this->actingAs($this->user)
        ->get(route('reports.invoice-summary.index'))
        ->assertSuccessful();
});

test('invoice summary loads with default nestle supplier', function () {
    $this->actingAs($this->user)
        ->get(route('reports.invoice-summary.index'))
        ->assertSuccessful()
        ->assertSee('Nestlé Pakistan');
});

test('invoice summary requires authentication', function () {
    $this->get(route('reports.invoice-summary.index'))
        ->assertRedirect(route('login'));
});

test('invoice summary requires permission', function () {
    $userWithoutPermission = User::factory()->create();

    $this->actingAs($userWithoutPermission)
        ->get(route('reports.invoice-summary.index'))
        ->assertForbidden();
});

test('invoice summary shows entries for selected supplier', function () {
    InvoiceSummary::factory()->create([
        'supplier_id' => $this->supplier->id,
        'invoice_date' => now()->format('Y-m-d'),
        'invoice_number' => 'INV-TEST-001',
        'invoice_value' => 500000,
    ]);

    $this->actingAs($this->user)
        ->get(route('reports.invoice-summary.index', [
            'filter' => ['supplier_id' => $this->supplier->id],
        ]))
        ->assertSuccessful()
        ->assertSee('INV-TEST-001')
        ->assertSee('500,000.00');
});

test('invoice summary filters by date range', function () {
    InvoiceSummary::factory()->create([
        'supplier_id' => $this->supplier->id,
        'invoice_date' => '2026-01-15',
        'invoice_number' => 'INV-JAN',
        'invoice_value' => 100000,
    ]);
    InvoiceSummary::factory()->create([
        'supplier_id' => $this->supplier->id,
        'invoice_date' => '2026-02-15',
        'invoice_number' => 'INV-FEB',
        'invoice_value' => 200000,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('reports.invoice-summary.index', [
            'filter' => [
                'supplier_id' => $this->supplier->id,
                'date_from' => '2026-02-01',
                'date_to' => '2026-02-28',
            ],
        ]));

    $response->assertSuccessful()
        ->assertSee('INV-FEB')
        ->assertDontSee('INV-JAN');
});

test('invoice summary filters by invoice number', function () {
    InvoiceSummary::factory()->create([
        'supplier_id' => $this->supplier->id,
        'invoice_date' => now()->format('Y-m-d'),
        'invoice_number' => '1073527810',
    ]);
    InvoiceSummary::factory()->create([
        'supplier_id' => $this->supplier->id,
        'invoice_date' => now()->format('Y-m-d'),
        'invoice_number' => '9999999999',
    ]);

    $this->actingAs($this->user)
        ->get(route('reports.invoice-summary.index', [
            'filter' => [
                'supplier_id' => $this->supplier->id,
                'invoice_number' => '1073527810',
            ],
        ]))
        ->assertSuccessful()
        ->assertSee('1073527810')
        ->assertDontSee('9999999999');
});

test('can store a new invoice summary entry', function () {
    $this->actingAs($this->user)
        ->post(route('reports.invoice-summary.store'), [
            'supplier_id' => $this->supplier->id,
            'invoice_date' => '2026-02-04',
            'invoice_number' => '1073527810',
            'cartons' => 1282,
            'invoice_value' => 11199451.37,
            'za_on_invoices' => 55997.26,
            'discount_value' => 434172.24,
            'fmr_allowance' => 36826.83,
            'discount_before_sales_tax' => 9427051.79,
            'excise_duty' => 46485.32,
            'sales_tax_value' => 1714725.96,
            'advance_tax' => 11188.30,
            'total_value_with_tax' => 11199451.37,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('invoice_summaries', [
        'supplier_id' => $this->supplier->id,
        'invoice_number' => '1073527810',
        'cartons' => 1282,
    ]);
});

test('can update an invoice summary entry', function () {
    $entry = InvoiceSummary::factory()->create([
        'supplier_id' => $this->supplier->id,
        'invoice_date' => '2026-02-04',
        'invoice_number' => 'INV-OLD',
        'cartons' => 100,
    ]);

    $this->actingAs($this->user)
        ->put(route('reports.invoice-summary.update', $entry), [
            'supplier_id' => $this->supplier->id,
            'invoice_date' => '2026-02-04',
            'invoice_number' => 'INV-UPDATED',
            'cartons' => 200,
        ])
        ->assertRedirect();

    $entry->refresh();
    expect($entry->invoice_number)->toBe('INV-UPDATED');
    expect($entry->cartons)->toBe(200);
});

test('can delete an invoice summary entry', function () {
    $entry = InvoiceSummary::factory()->create([
        'supplier_id' => $this->supplier->id,
    ]);

    $this->actingAs($this->user)
        ->delete(route('reports.invoice-summary.destroy', $entry))
        ->assertRedirect();

    $this->assertSoftDeleted('invoice_summaries', ['id' => $entry->id]);
});

test('store requires authentication', function () {
    $this->post(route('reports.invoice-summary.store'), [
        'supplier_id' => $this->supplier->id,
        'invoice_date' => '2026-02-04',
        'invoice_number' => '1073527810',
    ])->assertRedirect(route('login'));
});

test('store requires manage permission', function () {
    $viewOnlyUser = User::factory()->create();
    $viewOnlyUser->givePermissionTo('report-audit-invoice-summary');

    $this->actingAs($viewOnlyUser)
        ->post(route('reports.invoice-summary.store'), [
            'supplier_id' => $this->supplier->id,
            'invoice_date' => '2026-02-04',
            'invoice_number' => '1073527810',
        ])
        ->assertForbidden();
});

test('store validates required fields', function () {
    $this->actingAs($this->user)
        ->post(route('reports.invoice-summary.store'), [])
        ->assertSessionHasErrors(['supplier_id', 'invoice_date', 'invoice_number']);
});

test('invoice summary displays column totals', function () {
    InvoiceSummary::factory()->create([
        'supplier_id' => $this->supplier->id,
        'invoice_date' => now()->format('Y-m-d'),
        'cartons' => 100,
        'invoice_value' => 500000,
        'sales_tax_value' => 90000,
    ]);
    InvoiceSummary::factory()->create([
        'supplier_id' => $this->supplier->id,
        'invoice_date' => now()->format('Y-m-d'),
        'cartons' => 200,
        'invoice_value' => 300000,
        'sales_tax_value' => 54000,
    ]);

    $this->actingAs($this->user)
        ->get(route('reports.invoice-summary.index', [
            'filter' => ['supplier_id' => $this->supplier->id],
        ]))
        ->assertSuccessful()
        ->assertSee('800,000.00');
});

test('invoice summary supports all per page option', function () {
    InvoiceSummary::factory()->count(5)->create([
        'supplier_id' => $this->supplier->id,
        'invoice_date' => now()->format('Y-m-d'),
    ]);

    $this->actingAs($this->user)
        ->get(route('reports.invoice-summary.index', [
            'filter' => ['supplier_id' => $this->supplier->id],
            'per_page' => 'all',
        ]))
        ->assertSuccessful();
});
