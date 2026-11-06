<?php

use App\Models\User;

it('sends the user back with a plain message when a save carries more data than the server accepts, instead of a bare 413', function () {
    $this->actingAs(User::factory()->create(['is_super_admin' => 'Yes']));

    $response = $this->from(route('dashboard'))->call('POST', route('expense-vouchers.store'), [], [], [], ['CONTENT_LENGTH' => 99 * 1024 ** 3]);

    $response->assertRedirect(route('dashboard'));
    expect(session('error'))->toContain('too large to save together')->toContain('post_max_size');
});

it('keeps a normal JSON request on the standard 413 answer', function () {
    $this->actingAs(User::factory()->create(['is_super_admin' => 'Yes']));

    $this->call('POST', route('expense-vouchers.store'), [], [], [], ['CONTENT_LENGTH' => 99 * 1024 ** 3, 'HTTP_ACCEPT' => 'application/json'])->assertStatus(413);
});
