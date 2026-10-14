<?php

use App\Models\Departamento;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;

/*
 * GET /api/user: datos del usuario autenticado que necesita la SPA
 * (identidad, rol y departamentos asignados).
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('guests can not retrieve the authenticated user', function () {
    $this->getJson('/api/user')->assertUnauthorized();
});

test('authenticated user is returned with role and assigned departamentos', function () {
    $user = User::factory()->create([
        'role_id' => Role::where('slug', 'encargado-departamento')->value('id'),
    ]);
    $compras = Departamento::create(['nombre' => 'Compras', 'slug' => 'compras']);
    Departamento::create(['nombre' => 'Admisión', 'slug' => 'admision']);
    $user->departamentos()->attach($compras);

    $response = $this->actingAs($user)->getJson('/api/user');

    $response->assertOk()
        ->assertExactJson([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'activo' => true,
                'role' => [
                    'id' => $user->role_id,
                    'nombre' => 'Encargado de Departamento/Proceso',
                    'slug' => 'encargado-departamento',
                ],
                'departamentos' => [
                    ['id' => $compras->id, 'nombre' => 'Compras', 'slug' => 'compras'],
                ],
            ],
            'meta' => [
                'sesion_minutos' => 4,
            ],
        ]);
});

test('session lifetime defaults to four minutes of inactivity', function () {
    // phpunit.xml no define SESSION_LIFETIME: se comprueba el valor por defecto.
    expect(config('session.lifetime'))->toBe(4);
});

test('session lifetime sent to the frontend follows the configuration', function () {
    config(['session.lifetime' => 15]);

    $this->actingAs(User::factory()->create())->getJson('/api/user')
        ->assertJsonPath('meta.sesion_minutos', 15);
});

test('user without role returns null role and empty departamentos', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('data.role', null)
        ->assertJsonPath('data.departamentos', []);
});

test('sensitive attributes are never exposed', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/user')->assertOk();

    foreach (['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'] as $campo) {
        $response->assertJsonMissingPath("data.{$campo}");
    }
});
