<?php

use App\Models\Departamento;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;

/*
 * Control de acceso por departamento (DepartamentoPolicy::view).
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->departamento = Departamento::create(['nombre' => 'Compras', 'slug' => 'compras']);
    $this->otroDepartamento = Departamento::create(['nombre' => 'Admisión', 'slug' => 'admision']);
});

function usuarioConRol(string $slug): User
{
    return User::factory()->create([
        'role_id' => Role::where('slug', $slug)->value('id'),
    ]);
}

test('guests can not view a departamento', function () {
    $this->getJson("/api/departamentos/{$this->departamento->id}")->assertUnauthorized();
});

test('administrador can view any departamento', function () {
    $this->actingAs(usuarioConRol('administrador'))
        ->getJson("/api/departamentos/{$this->departamento->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $this->departamento->id);
});

test('encargado can view an assigned departamento', function (string $rol) {
    $user = usuarioConRol($rol);
    $user->departamentos()->attach($this->departamento);

    $this->actingAs($user)
        ->getJson("/api/departamentos/{$this->departamento->id}")
        ->assertOk();
})->with(['encargado-departamento', 'encargado-seguimiento']);

test('encargado can not view a departamento not assigned to them', function (string $rol) {
    $user = usuarioConRol($rol);
    $user->departamentos()->attach($this->departamento);

    $this->actingAs($user)
        ->getJson("/api/departamentos/{$this->otroDepartamento->id}")
        ->assertForbidden();
})->with(['encargado-departamento', 'encargado-seguimiento']);

test('read-only roles can view any departamento', function (string $rol) {
    $this->actingAs(usuarioConRol($rol))
        ->getJson("/api/departamentos/{$this->otroDepartamento->id}")
        ->assertOk();
})->with(['auditor', 'direccion']);

test('users without role can not view a departamento', function () {
    $this->actingAs(User::factory()->create())
        ->getJson("/api/departamentos/{$this->departamento->id}")
        ->assertForbidden();
});

// --- Listado -----------------------------------------------------------------

test('guests can not list departamentos', function () {
    $this->getJson('/api/departamentos')->assertUnauthorized();
});

test('roles with global read access list every departamento', function (string $rol) {
    $this->actingAs(usuarioConRol($rol))
        ->getJson('/api/departamentos')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        // Ordenados por nombre.
        ->assertJsonPath('data.0.slug', 'admision')
        ->assertJsonPath('data.1.slug', 'compras')
        ->assertJsonStructure(['data' => [['id', 'nombre', 'slug', 'direccion', 'activo']]]);
})->with(['administrador', 'auditor', 'direccion']);

test('encargados only list their assigned departamentos', function (string $rol) {
    $user = usuarioConRol($rol);
    $user->departamentos()->attach($this->departamento);

    $this->actingAs($user)
        ->getJson('/api/departamentos')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->departamento->id);
})->with(['encargado-departamento', 'encargado-seguimiento']);

test('users without role list no departamentos', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/api/departamentos')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('departamentos can be filtered by active status', function () {
    Departamento::create(['nombre' => 'Archivo', 'slug' => 'archivo', 'activo' => false]);
    $admin = usuarioConRol('administrador');

    $this->actingAs($admin)->getJson('/api/departamentos')->assertJsonCount(3, 'data');
    $this->actingAs($admin)->getJson('/api/departamentos?activo=1')->assertJsonCount(2, 'data');
    $this->actingAs($admin)->getJson('/api/departamentos?activo=0')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'archivo');
});

test('active filter rejects invalid values', function () {
    $this->actingAs(usuarioConRol('administrador'))
        ->getJson('/api/departamentos?activo=quizas')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('activo');
});

test('missing departamento returns not found', function () {
    $this->actingAs(usuarioConRol('administrador'))
        ->getJson('/api/departamentos/9999')
        ->assertNotFound();
});
