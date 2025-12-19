<?php

use App\Models\Customer;

use function Pest\Laravel\get;

it('legacy customer balance endpoints are removed', function () {
    $customer = Customer::factory()->create([
        'customer_code' => 'C-TEST',
        'customer_name' => 'Test Customer',
        'is_active' => true,
    ]);

    get("/api/customers/{$customer->id}/balance")
        ->assertNotFound();

    get("/api/customers/{$customer->id}/balance-by-employee/1")
        ->assertNotFound();

    get('/api/customers/by-employee/1')
        ->assertNotFound();
});
