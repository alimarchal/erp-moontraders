<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('login screen has a show/hide toggle on the password field', function () {
    $this->get('/login')
        ->assertSuccessful()
        ->assertSee('x-bind:type="show ? \'text\' : \'password\'"', false)
        ->assertSee('type="password"', false)
        ->assertSee('aria-label="Show password"', false);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users cannot authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('inactive users cannot authenticate from login screen', function () {
    $user = User::factory()->create(['is_active' => 'No']);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors([
        'email' => 'Your account has been deactivated. Please contact the administrator.',
    ]);
});

test('authenticated inactive users are logged out when visiting dashboard', function () {
    $user = User::factory()->create(['is_active' => 'Yes']);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $user->update(['is_active' => 'No']);
    Auth::forgetGuards();

    $response = $this->get('/dashboard');

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors([
        'email' => 'Your account has been deactivated. Please contact the administrator.',
    ]);

    Auth::forgetGuards();
    $this->assertGuest('web');
});
