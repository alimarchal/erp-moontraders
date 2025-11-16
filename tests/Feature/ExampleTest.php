<?php

use App\Models\User;

it('redirects unauthenticated users to login', function () {
    $response = $this->get('/');

    $response->assertRedirect('/login');
});

it('authenticated users can access dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertStatus(200);
});
