<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

/*
 * Flujo de autenticación SPA (Nuxt + Sanctum con cookies de sesión).
 * Las peticiones se envían como JSON, igual que lo hace el frontend.
 */

test('users can authenticate with valid credentials', function () {
    $user = User::factory()->create();

    $response = $this->postJson(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertOk()->assertJson(['two_factor' => false]);
    $this->assertAuthenticatedAs($user);
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $response = $this->postJson(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->assertGuest();
});

test('users can not authenticate with an unknown email', function () {
    $response = $this->postJson(route('login.store'), [
        'email' => 'nadie@infotep.test',
        'password' => 'password',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->assertGuest();
});

test('inactive users can not authenticate', function () {
    $user = User::factory()->create(['activo' => false]);

    $response = $this->postJson(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    // Mismo error que con credenciales incorrectas: no se revela el estado de la cuenta.
    $response->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->assertGuest();
});

test('an open session is closed once the user is deactivated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/user')->assertOk();

    $user->update(['activo' => false]);

    $this->actingAs($user->fresh())->getJson('/api/user')->assertUnauthorized();
    $this->assertGuest('web');
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('logout'));

    $response->assertNoContent();
    $this->assertGuest('web');
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->postJson(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});

test('guests visiting protected web routes are redirected to the frontend login', function () {
    // Ruta web de Fortify protegida con 'auth', pedida desde el navegador (no JSON).
    $this->get(route('password.confirmation'))
        ->assertRedirect(config('app.frontend_url').'/login');
});
