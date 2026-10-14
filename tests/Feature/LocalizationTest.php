<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;

/*
 * Los mensajes que ve el usuario en los formularios llegan desde Laravel:
 * deben estar en español y con nombres de campo comprensibles.
 */

test('application locale is spanish', function () {
    expect(app()->getLocale())->toBe('es');
});

test('failed login message is in spanish', function () {
    $user = User::factory()->create();

    $this->postJson(route('login.store'), ['email' => $user->email, 'password' => 'incorrecta'])
        ->assertJsonValidationErrors(['email' => 'Estas credenciales no coinciden con nuestros registros.']);
});

test('validation messages use spanish attribute names', function () {
    $this->seed(RoleSeeder::class);
    $admin = User::factory()->create(['role_id' => Role::where('slug', 'administrador')->value('id')]);

    $this->actingAs($admin)->postJson('/api/users', [
        'email' => 'no-es-email',
        'password' => 'clave-segura-123',
        'password_confirmation' => 'otra',
        'departamento_ids' => [9999],
    ])->assertJsonValidationErrors([
        'name' => 'El campo nombre es obligatorio.',
        'email' => 'El campo correo electrónico debe ser una dirección de correo válida.',
        'password' => 'La confirmación del campo contraseña no coincide.',
        'role_id' => 'El campo rol es obligatorio.',
        'departamento_ids.0' => 'El departamento seleccionado no es válido.',
    ]);
});
