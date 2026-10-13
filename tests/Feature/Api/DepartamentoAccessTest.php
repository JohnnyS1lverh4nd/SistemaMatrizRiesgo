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

test('missing departamento returns not found', function () {
    $this->actingAs(usuarioConRol('administrador'))
        ->getJson('/api/departamentos/9999')
        ->assertNotFound();
});
