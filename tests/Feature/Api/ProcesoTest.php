<?php

use App\Models\Departamento;
use App\Models\Matriz;
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

test('other roles can not create procesos, even in their departamento', function (string $rol) {
    $user = usuarioConRolYDepartamento($rol, $this->compras);

    $this->actingAs($user)->postJson("/api/departamentos/{$this->compras->id}/procesos", ['nombre' => 'Nuevo'])
        ->assertForbidden();

    expect(Proceso::where('slug', 'nuevo')->exists())->toBeFalse();
})->with(['encargado-seguimiento', 'auditor', 'direccion']);

// --- Alta por el Encargado de Departamento (nueva matriz, RF-2) -------------

test('an encargado de departamento can create a proceso in an assigned departamento', function () {
    $encargado = usuarioConRolYDepartamento('encargado-departamento', $this->compras);

    $respuesta = $this->actingAs($encargado)->postJson("/api/departamentos/{$this->compras->id}/procesos", [
        'nombre' => 'Gestión de Almacén',
        'descripcion' => 'Recepción y despacho de materiales.',
    ])->assertCreated()
        ->assertJsonPath('data.departamento_id', $this->compras->id)
        ->assertJsonPath('data.permisos.editar', true)
        ->assertJsonPath('data.permisos.crear_matriz', true);

    $proceso = Proceso::findOrFail($respuesta->json('data.id'));
    expect($proceso->creado_por_id)->toBe($encargado->id)
        ->and($proceso->matrices()->count())->toBe(0);
});

test('the creator is recorded also when the admin creates a proceso', function () {
    $respuesta = $this->actingAs($this->admin)->postJson("/api/departamentos/{$this->compras->id}/procesos", ['nombre' => 'Nuevo'])
        ->assertCreated();

    expect(Proceso::findOrFail($respuesta->json('data.id'))->creado_por_id)->toBe($this->admin->id);
});

test('an encargado can not create procesos in a departamento not assigned to them', function () {
    $encargado = usuarioConRolYDepartamento('encargado-departamento', $this->compras);

    // IDOR: conoce el id del departamento ajeno.
    $this->actingAs($encargado)->postJson("/api/departamentos/{$this->admision->id}/procesos", ['nombre' => 'Intruso'])
        ->assertForbidden();

    expect(Proceso::where('slug', 'intruso')->exists())->toBeFalse();
});

test('an encargado can not create procesos in an inactive departamento', function () {
    $encargado = usuarioConRolYDepartamento('encargado-departamento', $this->compras);
    $this->compras->update(['activo' => false]);

    $this->actingAs($encargado)->postJson("/api/departamentos/{$this->compras->id}/procesos", ['nombre' => 'Nuevo'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('departamento');
});

test('the creator can not be forged from the request body', function () {
    $encargado = usuarioConRolYDepartamento('encargado-departamento', $this->compras);

    $respuesta = $this->actingAs($encargado)->postJson("/api/departamentos/{$this->compras->id}/procesos", [
        'nombre' => 'Nuevo',
        'creado_por_id' => $this->admin->id,
    ])->assertCreated();

    expect(Proceso::findOrFail($respuesta->json('data.id'))->creado_por_id)->toBe($encargado->id);
});

test('a duplicated proceso name in the same departamento is rejected with a clear message', function () {
    $encargado = usuarioConRolYDepartamento('encargado-departamento', $this->compras);

    $this->actingAs($encargado)->postJson("/api/departamentos/{$this->compras->id}/procesos", ['nombre' => 'Abastecimiento'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['slug' => 'Ya existe un proceso con ese nombre en este departamento.']);
});

// --- Primera edición en la misma operación ---------------------------------

test('a proceso can be created together with its first draft edition', function () {
    $encargado = usuarioConRolYDepartamento('encargado-departamento', $this->compras);

    $respuesta = $this->actingAs($encargado)->postJson("/api/departamentos/{$this->compras->id}/procesos", [
        'nombre' => 'Gestión de Almacén',
        'edicion_inicial' => ['nombre' => 'Enero-Marzo 2027', 'fecha_revision' => '2027-01-15'],
    ])->assertCreated();

    $proceso = Proceso::findOrFail($respuesta->json('data.id'));
    $edicion = $proceso->matrices()->sole();

    expect($edicion->nombre)->toBe('Enero-Marzo 2027')
        ->and($edicion->fecha_revision->toDateString())->toBe('2027-01-15')
        ->and($edicion->estado->value)->toBe('borrador')
        ->and($edicion->edicion_anterior_id)->toBeNull();
});

test('an invalid first edition creates nothing', function () {
    $encargado = usuarioConRolYDepartamento('encargado-departamento', $this->compras);

    $this->actingAs($encargado)->postJson("/api/departamentos/{$this->compras->id}/procesos", [
        'nombre' => 'Gestión de Almacén',
        'edicion_inicial' => ['nombre' => '', 'fecha_revision' => '15/01/2027'],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['edicion_inicial.nombre', 'edicion_inicial.fecha_revision']);

    expect(Proceso::where('slug', 'gestion-de-almacen')->exists())->toBeFalse();
});

test('the first edition can not be created already published', function () {
    $encargado = usuarioConRolYDepartamento('encargado-departamento', $this->compras);

    $this->actingAs($encargado)->postJson("/api/departamentos/{$this->compras->id}/procesos", [
        'nombre' => 'Gestión de Almacén',
        'edicion_inicial' => ['nombre' => 'Edición 1', 'fecha_revision' => '2027-01-15', 'estado' => 'vigente'],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('edicion_inicial.estado');
});

test('if saving the first edition fails, the proceso is rolled back', function () {
    $encargado = usuarioConRolYDepartamento('encargado-departamento', $this->compras);

    // Simula un fallo de base de datos al crear la edición.
    Matriz::creating(fn () => throw new RuntimeException('fallo simulado'));

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($encargado)->postJson("/api/departamentos/{$this->compras->id}/procesos", [
        'nombre' => 'Gestión de Almacén',
        'edicion_inicial' => ['nombre' => 'Edición 1', 'fecha_revision' => '2027-01-15'],
    ]))->toThrow(RuntimeException::class);

    expect(Proceso::where('slug', 'gestion-de-almacen')->exists())->toBeFalse();
});

test('creating a proceso and its first edition is audited', function () {
    $encargado = usuarioConRolYDepartamento('encargado-departamento', $this->compras);

    $respuesta = $this->actingAs($encargado)->postJson("/api/departamentos/{$this->compras->id}/procesos", [
        'nombre' => 'Gestión de Almacén',
        'edicion_inicial' => ['nombre' => 'Edición 1', 'fecha_revision' => '2027-01-15'],
    ])->assertCreated();

    $proceso = Proceso::findOrFail($respuesta->json('data.id'));

    $this->assertDatabaseHas('auditoria', ['evento' => 'creado', 'entidad' => 'proceso', 'entidad_id' => $proceso->id, 'user_id' => $encargado->id]);
    $this->assertDatabaseHas('auditoria', ['evento' => 'creado', 'entidad' => 'matriz', 'entidad_id' => $proceso->matrices()->sole()->id, 'user_id' => $encargado->id]);
});

test('departamentos tell the frontend who can create procesos in them', function () {
    $encargado = usuarioConRolYDepartamento('encargado-departamento', $this->compras);
    $seguimiento = usuarioConRolYDepartamento('encargado-seguimiento', $this->compras);

    $this->actingAs($encargado)->getJson("/api/departamentos/{$this->compras->id}")
        ->assertJsonPath('data.permisos.crear_proceso', true);
    $this->actingAs($seguimiento)->getJson("/api/departamentos/{$this->compras->id}")
        ->assertJsonPath('data.permisos.crear_proceso', false);
    $this->actingAs($this->admin)->getJson("/api/departamentos/{$this->admision->id}")
        ->assertJsonPath('data.permisos.crear_proceso', true);
});

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

test('an encargado can not update procesos created by someone else', function () {
    $user = usuarioConRolYDepartamento('encargado-departamento', $this->compras);

    $this->actingAs($user)->putJson("/api/procesos/{$this->abastecimiento->id}", ['nombre' => 'Cambiado'])
        ->assertForbidden();
    $this->actingAs($user)->getJson("/api/procesos/{$this->abastecimiento->id}")
        ->assertJsonPath('data.permisos.editar', false);

    expect($this->abastecimiento->fresh()->nombre)->toBe('SP 4.3. Abastecimiento');
});

test('other roles can not update procesos', function (string $rol) {
    $user = usuarioConRolYDepartamento($rol, $this->compras);

    $this->actingAs($user)->putJson("/api/procesos/{$this->abastecimiento->id}", ['nombre' => 'Cambiado'])
        ->assertForbidden();
})->with(['encargado-seguimiento', 'auditor', 'direccion']);

test('an encargado can update and deactivate the procesos they created', function () {
    $encargado = usuarioConRolYDepartamento('encargado-departamento', $this->compras);
    $propio = Proceso::create(['departamento_id' => $this->compras->id, 'nombre' => 'Propio', 'slug' => 'propio']);
    $propio->forceFill(['creado_por_id' => $encargado->id])->save();

    $this->actingAs($encargado)->putJson("/api/procesos/{$propio->id}", [
        'nombre' => 'Propio renombrado',
        'descripcion' => 'Nueva descripción',
        'activo' => false,
    ])->assertOk()
        ->assertJsonPath('data.nombre', 'Propio renombrado')
        ->assertJsonPath('data.activo', false);

    $this->actingAs($encargado)->putJson("/api/procesos/{$propio->id}", ['activo' => true])
        ->assertOk()
        ->assertJsonPath('data.activo', true);
});

test('an encargado loses edit rights on their proceso when unassigned from its departamento', function () {
    $encargado = usuarioConRolYDepartamento('encargado-departamento', $this->compras);
    $propio = Proceso::create(['departamento_id' => $this->compras->id, 'nombre' => 'Propio', 'slug' => 'propio']);
    $propio->forceFill(['creado_por_id' => $encargado->id])->save();

    $encargado->departamentos()->detach($this->compras);

    $this->actingAs($encargado->fresh())->putJson("/api/procesos/{$propio->id}", ['nombre' => 'Cambiado'])
        ->assertForbidden();
});

test('the creator of a proceso can not be changed through the api', function () {
    $encargado = usuarioConRolYDepartamento('encargado-departamento', $this->compras);
    $propio = Proceso::create(['departamento_id' => $this->compras->id, 'nombre' => 'Propio', 'slug' => 'propio']);
    $propio->forceFill(['creado_por_id' => $encargado->id])->save();

    $this->actingAs($this->admin)->putJson("/api/procesos/{$propio->id}", ['creado_por_id' => $this->admin->id])
        ->assertOk();

    expect($propio->fresh()->creado_por_id)->toBe($encargado->id);
});

test('procesos can not be deleted', function () {
    $this->actingAs($this->admin)->deleteJson("/api/procesos/{$this->abastecimiento->id}")->assertMethodNotAllowed();
    $this->assertModelExists($this->abastecimiento);
});
