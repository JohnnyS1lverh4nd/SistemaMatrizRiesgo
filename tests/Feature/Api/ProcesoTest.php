<?php

use App\Models\Departamento;
use App\Models\Proceso;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;

/*
 * Fase 2: procesos de cada departamento. Consulta según el acceso al
 * departamento; alta y edición solo para el Administrador; sin borrado.
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->create(['role_id' => Role::where('slug', 'administrador')->value('id')]);

    $this->compras = Departamento::create(['nombre' => 'Compras', 'slug' => 'compras']);
    $this->admision = Departamento::create(['nombre' => 'Admisión', 'slug' => 'admision']);

    $this->abastecimiento = Proceso::create([
        'departamento_id' => $this->compras->id,
        'nombre' => 'SP 4.3. Abastecimiento',
        'slug' => 'abastecimiento',
    ]);
    $this->formacion = Proceso::create([
        'departamento_id' => $this->admision->id,
        'nombre' => 'Formación Profesional',
        'slug' => 'formacion-profesional',
    ]);
});

function usuarioConRolYDepartamento(string $rol, ?Departamento $departamento = null): User
{
    $user = User::factory()->create(['role_id' => Role::where('slug', $rol)->value('id')]);

    if ($departamento) {
        $user->departamentos()->attach($departamento);
    }

    return $user;
}

// --- Consulta -------------------------------------------------------------

test('guests can not list procesos', function () {
    $this->getJson("/api/departamentos/{$this->compras->id}/procesos")->assertUnauthorized();
});

test('procesos are listed per departamento', function () {
    $this->actingAs($this->admin)->getJson("/api/departamentos/{$this->compras->id}/procesos")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->abastecimiento->id)
        ->assertJsonPath('data.0.departamento_id', $this->compras->id);
});

test('encargados see procesos of their own departamento only', function (string $rol) {
    $user = usuarioConRolYDepartamento($rol, $this->compras);

    $this->actingAs($user)->getJson("/api/departamentos/{$this->compras->id}/procesos")->assertOk();
    $this->actingAs($user)->getJson("/api/procesos/{$this->abastecimiento->id}")->assertOk();

    // IDOR: departamento y proceso ajenos, aunque conozca sus ids.
    $this->actingAs($user)->getJson("/api/departamentos/{$this->admision->id}/procesos")->assertForbidden();
    $this->actingAs($user)->getJson("/api/procesos/{$this->formacion->id}")->assertForbidden();
})->with(['encargado-departamento', 'encargado-seguimiento']);

test('read only roles see procesos of every departamento', function (string $rol) {
    $user = usuarioConRolYDepartamento($rol);

    $this->actingAs($user)->getJson("/api/departamentos/{$this->admision->id}/procesos")->assertOk();
    $this->actingAs($user)->getJson("/api/procesos/{$this->formacion->id}")
        ->assertOk()
        ->assertJsonPath('data.nombre', 'Formación Profesional');
})->with(['auditor', 'direccion']);

test('procesos can be filtered by active status', function () {
    Proceso::create(['departamento_id' => $this->compras->id, 'nombre' => 'Antiguo', 'slug' => 'antiguo', 'activo' => false]);

    $this->actingAs($this->admin)->getJson("/api/departamentos/{$this->compras->id}/procesos?activo=1")
        ->assertJsonCount(1, 'data');
    $this->actingAs($this->admin)->getJson("/api/departamentos/{$this->compras->id}/procesos?activo=0")
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'antiguo');
});

// --- Alta -----------------------------------------------------------------

test('admin can create a proceso in a departamento', function () {
    $this->actingAs($this->admin)->postJson("/api/departamentos/{$this->compras->id}/procesos", [
        'nombre' => 'Gestión de Almacén',
        'descripcion' => 'Recepción y despacho de materiales.',
    ])->assertCreated()
        ->assertJsonPath('data.departamento_id', $this->compras->id)
        ->assertJsonPath('data.slug', 'gestion-de-almacen')
        ->assertJsonPath('data.activo', true);
});

test('the same proceso slug can exist in different departamentos', function () {
    // "Formación Profesional" existe en varias unidades (Admisión, CTIVPR, SFP).
    $this->actingAs($this->admin)->postJson("/api/departamentos/{$this->compras->id}/procesos", [
        'nombre' => 'Formación Profesional',
    ])->assertCreated()
        ->assertJsonPath('data.slug', 'formacion-profesional');
});

test('proceso slug must be unique within its departamento', function () {
    $this->actingAs($this->admin)->postJson("/api/departamentos/{$this->compras->id}/procesos", [
        'nombre' => 'Otro',
        'slug' => 'abastecimiento',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('slug');
});

test('procesos can not be created in an inactive departamento', function () {
    $this->compras->update(['activo' => false]);

    $this->actingAs($this->admin)->postJson("/api/departamentos/{$this->compras->id}/procesos", ['nombre' => 'Nuevo'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('departamento');
});

test('non admin roles can not create procesos, even in their departamento', function (string $rol) {
    $user = usuarioConRolYDepartamento($rol, $this->compras);

    $this->actingAs($user)->postJson("/api/departamentos/{$this->compras->id}/procesos", ['nombre' => 'Nuevo'])
        ->assertForbidden();
})->with(['encargado-departamento', 'encargado-seguimiento', 'auditor', 'direccion']);

// --- Edición --------------------------------------------------------------

test('admin can update and deactivate a proceso', function () {
    $this->actingAs($this->admin)->putJson("/api/procesos/{$this->abastecimiento->id}", [
        'nombre' => 'SP 4.3. Abastecimiento y Compras',
        'activo' => false,
    ])->assertOk()
        ->assertJsonPath('data.nombre', 'SP 4.3. Abastecimiento y Compras')
        ->assertJsonPath('data.activo', false);
});

test('a proceso can not be moved to another departamento', function () {
    $this->actingAs($this->admin)->putJson("/api/procesos/{$this->abastecimiento->id}", [
        'departamento_id' => $this->admision->id,
    ])->assertOk();

    expect($this->abastecimiento->fresh()->departamento_id)->toBe($this->compras->id);
});

test('non admin roles can not update procesos', function () {
    $user = usuarioConRolYDepartamento('encargado-departamento', $this->compras);

    $this->actingAs($user)->putJson("/api/procesos/{$this->abastecimiento->id}", ['nombre' => 'Cambiado'])
        ->assertForbidden();

    expect($this->abastecimiento->fresh()->nombre)->toBe('SP 4.3. Abastecimiento');
});

test('procesos can not be deleted', function () {
    $this->actingAs($this->admin)->deleteJson("/api/procesos/{$this->abastecimiento->id}")->assertMethodNotAllowed();
    $this->assertModelExists($this->abastecimiento);
});
