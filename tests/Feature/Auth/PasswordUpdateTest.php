<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/*
 * Cambio de la propia contraseña (Fortify updatePasswords: PUT /user/password).
 */

test('guests can not update a password', function () {
    $this->putJson(route('user-password.update'), [
        'current_password' => 'password',
        'password' => 'nueva-clave-123',
        'password_confirmation' => 'nueva-clave-123',
    ])->assertUnauthorized();
});

test('users can update their own password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->putJson(route('user-password.update'), [
        'current_password' => 'password',
        'password' => 'nueva-clave-123',
        'password_confirmation' => 'nueva-clave-123',
    ])->assertOk();

    expect(Hash::check('nueva-clave-123', $user->fresh()->password))->toBeTrue();
});

test('current password must be correct', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->putJson(route('user-password.update'), [
        'current_password' => 'incorrecta',
        'password' => 'nueva-clave-123',
        'password_confirmation' => 'nueva-clave-123',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['current_password' => 'La contraseña es incorrecta.']);

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('new password must be confirmed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->putJson(route('user-password.update'), [
        'current_password' => 'password',
        'password' => 'nueva-clave-123',
        'password_confirmation' => 'otra-cosa',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('password');
});

test('users can not change another user password through this endpoint', function () {
    $user = User::factory()->create();
    $otro = User::factory()->create();

    $this->actingAs($user)->putJson(route('user-password.update'), [
        'current_password' => 'password',
        'password' => 'nueva-clave-123',
        'password_confirmation' => 'nueva-clave-123',
        'id' => $otro->id,
        'email' => $otro->email,
    ])->assertOk();

    // Solo cambia la del usuario autenticado; los campos extra se ignoran.
    expect(Hash::check('password', $otro->fresh()->password))->toBeTrue();
});
