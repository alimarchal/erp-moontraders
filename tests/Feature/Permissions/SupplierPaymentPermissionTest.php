<?php

use App\Models\BankAccount;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['supplier-payment-list', 'supplier-payment-create', 'supplier-payment-edit', 'supplier-payment-delete', 'supplier-payment-post', 'supplier-payment-reverse'] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies index without supplier-payment-list permission', function () {
    $this->get(route('supplier-payments.index'))->assertForbidden();
});

it('allows index with supplier-payment-list permission', function () {
    $this->user->givePermissionTo('supplier-payment-list');
    $this->get(route('supplier-payments.index'))->assertSuccessful();
});

it('denies create without supplier-payment-create permission', function () {
    $this->get(route('supplier-payments.create'))->assertForbidden();
});

it('allows create with supplier-payment-create permission', function () {
    $this->user->givePermissionTo('supplier-payment-create');
    $this->get(route('supplier-payments.create'))->assertSuccessful();
});

it('denies store without supplier-payment-create permission', function () {
    $this->post(route('supplier-payments.store'), [])->assertForbidden();
});

it('denies show without supplier-payment-list permission', function () {
    $payment = SupplierPayment::create([
        'payment_number' => 'SP-TEST-001',
        'supplier_id' => Supplier::factory()->create()->id,
        'bank_account_id' => BankAccount::factory()->create()->id,
        'payment_date' => now(),
        'payment_method' => 'bank_transfer',
        'amount' => 1000,
        'status' => 'draft',
        'created_by' => $this->user->id,
    ]);
    $this->get(route('supplier-payments.show', $payment))->assertForbidden();
});

it('denies edit without supplier-payment-edit permission', function () {
    $payment = SupplierPayment::create([
        'payment_number' => 'SP-TEST-002',
        'supplier_id' => Supplier::factory()->create()->id,
        'bank_account_id' => BankAccount::factory()->create()->id,
        'payment_date' => now(),
        'payment_method' => 'bank_transfer',
        'amount' => 1000,
        'status' => 'draft',
        'created_by' => $this->user->id,
    ]);
    $this->get(route('supplier-payments.edit', $payment))->assertForbidden();
});

it('denies update without supplier-payment-edit permission', function () {
    $payment = SupplierPayment::create([
        'payment_number' => 'SP-TEST-003',
        'supplier_id' => Supplier::factory()->create()->id,
        'bank_account_id' => BankAccount::factory()->create()->id,
        'payment_date' => now(),
        'payment_method' => 'bank_transfer',
        'amount' => 1000,
        'status' => 'draft',
        'created_by' => $this->user->id,
    ]);
    $this->put(route('supplier-payments.update', $payment), [])->assertForbidden();
});

it('denies destroy without supplier-payment-delete permission', function () {
    $payment = SupplierPayment::create([
        'payment_number' => 'SP-TEST-004',
        'supplier_id' => Supplier::factory()->create()->id,
        'bank_account_id' => BankAccount::factory()->create()->id,
        'payment_date' => now(),
        'payment_method' => 'bank_transfer',
        'amount' => 1000,
        'status' => 'draft',
        'created_by' => $this->user->id,
    ]);
    $this->delete(route('supplier-payments.destroy', $payment))->assertForbidden();
});

it('denies post without supplier-payment-post permission', function () {
    $payment = SupplierPayment::create([
        'payment_number' => 'SP-TEST-005',
        'supplier_id' => Supplier::factory()->create()->id,
        'bank_account_id' => BankAccount::factory()->create()->id,
        'payment_date' => now(),
        'payment_method' => 'bank_transfer',
        'amount' => 1000,
        'status' => 'draft',
        'created_by' => $this->user->id,
    ]);
    $this->post(route('supplier-payments.post', $payment))->assertForbidden();
});

it('denies reverse without supplier-payment-reverse permission', function () {
    $payment = SupplierPayment::create([
        'payment_number' => 'SP-TEST-006',
        'supplier_id' => Supplier::factory()->create()->id,
        'bank_account_id' => BankAccount::factory()->create()->id,
        'payment_date' => now(),
        'payment_method' => 'bank_transfer',
        'amount' => 1000,
        'status' => 'draft',
        'created_by' => $this->user->id,
    ]);
    $this->post(route('supplier-payments.reverse', $payment))->assertForbidden();
});
