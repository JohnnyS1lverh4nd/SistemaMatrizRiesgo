<?php

use App\Models\Departamento;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;

/*
 * Fase 2: alta y edición de departamentos (solo Administrador), sin borrado.
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->create(['role_id' => Role::where('slug', 'administrador')->value('id')]);
});

function usuarioDeRol(string $slug): User
{
    return User::factory()->create(['role_id' => Role::where('slug', $slug)->value('id')]);
}

test('admin can create a departamento', function () {
    $this->actingAs($this->admin)->postJson('/api/departamentos', [
        'nombre' => 'División de Compras',
        'slug' => 'compras',
        'direccion' => 'Administración y Finanzas',
    ])->assertCreated()
        ->assertJsonPath('data.nombre', 'División de Compras')
        ->assertJsonPath('data.slug', 'compras')
        ->assertJsonPath('data.direccion', 'Administración y Finanzas')
        ->assertJsonPath('data.activo', true);

    expect(Departamento::where('slug', 'compras')->exists())->toBeTrue();
});

test('slug is generated from the name when omitted', function () {
    $this->actingAs($this->admin)->postJson('/api/departamentos', ['nombre' => 'Formación Profesional'])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'formacion-profesional');
});

test('departamento creation validates input', function (array $datos, string $campo) {
    Departamento::create(['nombre' => 'Compras', 'slug' => 'compras']);

    $this->actingAs($this->admin)->postJson('/api/departamentos', $datos)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($campo);
})->with([
    'sin nombre' => [['direccion' => 'X'], 'nombre'],
    'slug repetido' => [['nombre' => 'Otro', 'slug' => 'compras'], 'slug'],
    'slug generado repetido' => [['nombre' => 'Compras'], 'slug'],
    'slug con espacios' => [['nombre' => 'Otro', 'slug' => 'con espacios'], 'slug'],
    'activo no booleano' => [['nombre' => 'Otro', 'activo' => 'quizas'], 'activo'],
]);

test('admin can update and deactivate a departamento', function () {
    $departamento = Departamento::create(['nombre' => 'Compras', 'slug' => 'compras']);

    $this->actingAs($this->admin)->putJson("/api/departamentos/{$departamento->id}", [
        'direccion' => 'Administración y Finanzas',
        'activo' => false,
    ])->assertOk()
        ->assertJsonPath('data.direccion', 'Administración y Finanzas')
        ->assertJsonPath('data.activo', false)
        ->assertJsonPath('data.nombre', 'Compras');
});

test('slug stays unique when updating', function () {
    Departamento::create(['nombre' => 'Compras', 'slug' => 'compras']);
    $otro = Departamento::create(['nombre' => 'Admisión', 'slug' => 'admision']);

    $this->actingAs($this->admin)->putJson("/api/departamentos/{$otro->id}", ['slug' => 'compras'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('slug');

    // Reenviar su propio slug no es un conflicto.
    $this->actingAs($this->admin)->putJson("/api/departamentos/{$otro->id}", ['slug' => 'admision'])->assertOk();
});

test('departamentos can not be deleted', function () {
    $departamento = Departamento::create(['nombre' => 'Compras', 'slug' => 'compras']);

    $this->actingAs($this->admin)->deleteJson("/api/departamentos/{$departamento->id}")->assertMethodNotAllowed();
    $this->assertModelExists($departamento);
});

test('guests can not create departamentos', function () {
    $this->postJson('/api/departamentos', ['nombre' => 'X'])->assertUnauthorized();
});

test('non admin roles can not create or update departamentos', function (string $rol) {
    $user = usuarioDeRol($rol);
    $departamento = Departamento::create(['nombre' => 'Compras', 'slug' => 'compras']);
    $user->departamentos()->attach($departamento);

    $this->actingAs($user)->postJson('/api/departamentos', ['nombre' => 'Nuevo'])->assertForbidden();
    $this->actingAs($user)->putJson("/api/departamentos/{$departamento->id}", ['nombre' => 'Cambiado'])->assertForbidden();

    expect($departamento->fresh()->nombre)->toBe('Compras');
})->with(['encargado-departamento', 'encargado-seguimiento', 'auditor', 'direccion']);
