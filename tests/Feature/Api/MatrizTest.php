<?php

use App\Enums\EstadoMatriz;
use App\Models\Departamento;
use App\Models\Matriz;
use App\Models\Proceso;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;

/*
 * Fase 3: matrices de riesgo por edición/periodo.
 *
 * Reglas confirmadas con el responsable del proyecto (oct. 2026):
 * - Una sola edición vigente por proceso: publicar una nueva archiva la anterior.
 * - Borrador: editable. Vigente: solo seguimiento (Fase 6). Archivada: inmutable.
 * - Un borrador nunca publicado se puede descartar (eliminación lógica).
 * - Gestionan: el Administrador y el Encargado de Departamento en sus departamentos.
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->compras = Departamento::create(['nombre' => 'Compras', 'slug' => 'compras']);
    $this->admision = Departamento::create(['nombre' => 'Admisión', 'slug' => 'admision']);
    $this->abastecimiento = Proceso::create(['departamento_id' => $this->compras->id, 'nombre' => 'SP 4.3. Abastecimiento', 'slug' => 'abastecimiento']);
    $this->formacion = Proceso::create(['departamento_id' => $this->admision->id, 'nombre' => 'Formación Profesional', 'slug' => 'formacion']);

    $this->admin = usuarioMatriz('administrador');
    $this->encargado = usuarioMatriz('encargado-departamento', $this->compras);
});

function usuarioMatriz(string $rol, ?Departamento $departamento = null): User
{
    $user = User::factory()->create(['role_id' => Role::where('slug', $rol)->value('id')]);
    if ($departamento) {
        $user->departamentos()->attach($departamento);
    }

    return $user;
}

function edicion(Proceso $proceso, string $nombre, EstadoMatriz $estado = EstadoMatriz::Borrador, string $fecha = '2026-03-31'): Matriz
{
    // forceFill: el estado no es asignable desde la API; aquí se fija para preparar el escenario.
    $matriz = (new Matriz)->forceFill([
        'proceso_id' => $proceso->id,
        'nombre' => $nombre,
        'fecha_revision' => $fecha,
        'estado' => $estado,
    ]);
    $matriz->save();

    return $matriz;
}

// --- Consulta -------------------------------------------------------------

test('editions of a proceso are listed newest first', function () {
    edicion($this->abastecimiento, 'Edición 3', EstadoMatriz::Archivada, '2022-02-28');
    edicion($this->abastecimiento, 'Enero-Marzo 2026', EstadoMatriz::Vigente, '2026-03-31');
    edicion($this->abastecimiento, 'Abril-Junio 2026', EstadoMatriz::Borrador, '2026-06-30');

    $this->actingAs($this->encargado)->getJson("/api/procesos/{$this->abastecimiento->id}/matrices")
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.nombre', 'Abril-Junio 2026')
        ->assertJsonPath('data.0.estado', 'borrador')
        ->assertJsonPath('data.0.proceso_id', $this->abastecimiento->id);
});

test('editions can be filtered by state', function () {
    edicion($this->abastecimiento, 'Vieja', EstadoMatriz::Archivada);
    edicion($this->abastecimiento, 'Actual', EstadoMatriz::Vigente);

    $this->actingAs($this->encargado)->getJson("/api/procesos/{$this->abastecimiento->id}/matrices?estado=vigente")
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nombre', 'Actual');

    $this->actingAs($this->encargado)->getJson("/api/procesos/{$this->abastecimiento->id}/matrices?estado=inventado")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('estado');
});

test('encargados can not see matrices of other departamentos', function () {
    $ajena = edicion($this->formacion, 'Enero-Marzo 2026');

    $this->actingAs($this->encargado)->getJson("/api/procesos/{$this->formacion->id}/matrices")->assertForbidden();
    $this->actingAs($this->encargado)->getJson("/api/matrices/{$ajena->id}")->assertForbidden();
});

test('read only roles can see every matriz but not manage them', function (string $rol) {
    $user = usuarioMatriz($rol);
    $matriz = edicion($this->formacion, 'Enero-Marzo 2026');

    $this->actingAs($user)->getJson("/api/matrices/{$matriz->id}")->assertOk();
    $this->actingAs($user)->postJson("/api/procesos/{$this->formacion->id}/matrices", ['nombre' => 'X', 'fecha_revision' => '2026-06-30'])
        ->assertForbidden();
    $this->actingAs($user)->postJson("/api/matrices/{$matriz->id}/publicar")->assertForbidden();
})->with(['auditor', 'direccion']);

test('guests can not access matrices', function () {
    $this->getJson("/api/procesos/{$this->abastecimiento->id}/matrices")->assertUnauthorized();
});

test('responses tell the interface which actions the user can perform', function () {
    $borrador = edicion($this->abastecimiento, 'Abril-Junio 2026');
    $auditor = usuarioMatriz('auditor');

    $this->actingAs($this->encargado)->getJson("/api/matrices/{$borrador->id}")
        ->assertJsonPath('data.permisos.editar', true)
        ->assertJsonPath('data.permisos.publicar', true)
        ->assertJsonPath('data.permisos.archivar', false);
    $this->actingAs($this->encargado)->getJson("/api/procesos/{$this->abastecimiento->id}")
        ->assertJsonPath('data.permisos.crear_matriz', true);

    $this->actingAs($auditor)->getJson("/api/matrices/{$borrador->id}")
        ->assertJsonPath('data.permisos.editar', false)
        ->assertJsonPath('data.permisos.publicar', false);
    $this->actingAs($auditor)->getJson("/api/procesos/{$this->abastecimiento->id}")
        ->assertJsonPath('data.permisos.crear_matriz', false);
});

// --- Alta -------------------------------------------------------------------

test('encargado can create a draft edition for a proceso of their departamento', function () {
    $this->actingAs($this->encargado)->postJson("/api/procesos/{$this->abastecimiento->id}/matrices", [
        'nombre' => 'Abril-Junio 2026',
        'fecha_revision' => '2026-06-30',
    ])->assertCreated()
        ->assertJsonPath('data.estado', 'borrador')
        ->assertJsonPath('data.nombre', 'Abril-Junio 2026')
        ->assertJsonPath('data.fecha_revision', '2026-06-30')
        ->assertJsonPath('data.edicion_anterior_id', null);
});

test('encargados can not create editions in other departamentos', function () {
    $this->actingAs($this->encargado)->postJson("/api/procesos/{$this->formacion->id}/matrices", [
        'nombre' => 'Abril-Junio 2026',
        'fecha_revision' => '2026-06-30',
    ])->assertForbidden();
});

test('encargado de seguimiento can not create editions, even in their departamento', function () {
    $seguimiento = usuarioMatriz('encargado-seguimiento', $this->compras);

    $this->actingAs($seguimiento)->postJson("/api/procesos/{$this->abastecimiento->id}/matrices", [
        'nombre' => 'Abril-Junio 2026',
        'fecha_revision' => '2026-06-30',
    ])->assertForbidden();
});

test('edition creation validates input', function (array $datos, string $campo) {
    edicion($this->abastecimiento, 'Enero-Marzo 2026');

    $this->actingAs($this->encargado)->postJson("/api/procesos/{$this->abastecimiento->id}/matrices", $datos)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($campo);
})->with([
    'sin nombre' => [['fecha_revision' => '2026-06-30'], 'nombre'],
    'nombre repetido en el proceso' => [['nombre' => 'Enero-Marzo 2026', 'fecha_revision' => '2026-06-30'], 'nombre'],
    'sin fecha' => [['nombre' => 'Abril-Junio 2026'], 'fecha_revision'],
    'fecha inválida' => [['nombre' => 'Abril-Junio 2026', 'fecha_revision' => '31/06/2026'], 'fecha_revision'],
    'estado inyectado' => [['nombre' => 'Abril-Junio 2026', 'fecha_revision' => '2026-06-30', 'estado' => 'vigente'], 'estado'],
]);

test('the same edition name can exist in different procesos', function () {
    edicion($this->formacion, 'Enero-Marzo 2026');

    $this->actingAs($this->encargado)->postJson("/api/procesos/{$this->abastecimiento->id}/matrices", [
        'nombre' => 'Enero-Marzo 2026',
        'fecha_revision' => '2026-03-31',
    ])->assertCreated();
});

test('editions can not be created for inactive procesos', function () {
    $this->abastecimiento->update(['activo' => false]);

    $this->actingAs($this->encargado)->postJson("/api/procesos/{$this->abastecimiento->id}/matrices", [
        'nombre' => 'Abril-Junio 2026',
        'fecha_revision' => '2026-06-30',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('proceso');
});

// --- Edición de metadatos -------------------------------------------------

test('a draft edition can be updated', function () {
    $borrador = edicion($this->abastecimiento, 'Abril-Junio');

    $this->actingAs($this->encargado)->putJson("/api/matrices/{$borrador->id}", [
        'nombre' => 'Abril-Junio 2026',
        'fecha_revision' => '2026-06-30',
    ])->assertOk()
        ->assertJsonPath('data.nombre', 'Abril-Junio 2026');
});

test('published and archived editions can not be updated', function (EstadoMatriz $estado) {
    $matriz = edicion($this->abastecimiento, 'Enero-Marzo 2026', $estado);

    $this->actingAs($this->admin)->putJson("/api/matrices/{$matriz->id}", ['nombre' => 'Cambiado'])
        ->assertForbidden();

    expect($matriz->fresh()->nombre)->toBe('Enero-Marzo 2026');
})->with([EstadoMatriz::Vigente, EstadoMatriz::Archivada]);

test('state can only change through publish and archive', function () {
    $borrador = edicion($this->abastecimiento, 'Abril-Junio 2026');

    $this->actingAs($this->admin)->putJson("/api/matrices/{$borrador->id}", ['estado' => 'archivada'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('estado');

    expect($borrador->fresh()->estado)->toBe(EstadoMatriz::Borrador);
});

// --- Publicar y archivar ----------------------------------------------------

test('publishing a draft makes it the only current edition and archives the previous one', function () {
    $anterior = edicion($this->abastecimiento, 'Enero-Marzo 2026', EstadoMatriz::Vigente);
    $nueva = edicion($this->abastecimiento, 'Abril-Junio 2026');
    $otroProceso = edicion($this->formacion, 'Enero-Marzo 2026', EstadoMatriz::Vigente);

    $this->actingAs($this->encargado)->postJson("/api/matrices/{$nueva->id}/publicar")
        ->assertOk()
        ->assertJsonPath('data.estado', 'vigente');

    expect($nueva->fresh()->estado)->toBe(EstadoMatriz::Vigente)
        ->and($nueva->fresh()->publicada_at)->not->toBeNull()
        ->and($anterior->fresh()->estado)->toBe(EstadoMatriz::Archivada)
        ->and($anterior->fresh()->archivada_at)->not->toBeNull()
        // La vigente de otro proceso no se toca.
        ->and($otroProceso->fresh()->estado)->toBe(EstadoMatriz::Vigente)
        ->and(Matriz::where('proceso_id', $this->abastecimiento->id)->where('estado', EstadoMatriz::Vigente)->count())->toBe(1);
});

test('only drafts can be published', function (EstadoMatriz $estado) {
    $matriz = edicion($this->abastecimiento, 'Enero-Marzo 2026', $estado);

    $this->actingAs($this->admin)->postJson("/api/matrices/{$matriz->id}/publicar")->assertForbidden();
})->with([EstadoMatriz::Vigente, EstadoMatriz::Archivada]);

test('a current edition can be archived', function () {
    $vigente = edicion($this->abastecimiento, 'Enero-Marzo 2026', EstadoMatriz::Vigente);

    $this->actingAs($this->encargado)->postJson("/api/matrices/{$vigente->id}/archivar")
        ->assertOk()
        ->assertJsonPath('data.estado', 'archivada');
});

test('only current editions can be archived', function (EstadoMatriz $estado) {
    $matriz = edicion($this->abastecimiento, 'Enero-Marzo 2026', $estado);

    $this->actingAs($this->admin)->postJson("/api/matrices/{$matriz->id}/archivar")->assertForbidden();
})->with([EstadoMatriz::Borrador, EstadoMatriz::Archivada]);

test('encargados can not publish or archive editions of other departamentos', function () {
    $borrador = edicion($this->formacion, 'Abril-Junio 2026');
    $vigente = edicion($this->formacion, 'Enero-Marzo 2026', EstadoMatriz::Vigente);

    $this->actingAs($this->encargado)->postJson("/api/matrices/{$borrador->id}/publicar")->assertForbidden();
    $this->actingAs($this->encargado)->postJson("/api/matrices/{$vigente->id}/archivar")->assertForbidden();
});

// --- Descartar borradores ---------------------------------------------------

test('a draft can be discarded with soft delete', function () {
    $borrador = edicion($this->abastecimiento, 'Prueba');

    $this->actingAs($this->encargado)->deleteJson("/api/matrices/{$borrador->id}")->assertNoContent();

    $this->assertSoftDeleted($borrador);
    $this->actingAs($this->encargado)->getJson("/api/matrices/{$borrador->id}")->assertNotFound();
    // El nombre queda libre para una edición nueva.
    $this->actingAs($this->encargado)->postJson("/api/procesos/{$this->abastecimiento->id}/matrices", [
        'nombre' => 'Prueba',
        'fecha_revision' => '2026-06-30',
    ])->assertCreated();
});

test('published or archived editions can never be deleted', function (EstadoMatriz $estado) {
    $matriz = edicion($this->abastecimiento, 'Enero-Marzo 2026', $estado);

    $this->actingAs($this->admin)->deleteJson("/api/matrices/{$matriz->id}")->assertForbidden();
    $this->assertNotSoftDeleted($matriz);
})->with([EstadoMatriz::Vigente, EstadoMatriz::Archivada]);

// --- Clonar -----------------------------------------------------------------

test('cloning creates a new draft linked to its origin without changing the origin', function () {
    $origen = edicion($this->abastecimiento, 'Enero-Marzo 2026', EstadoMatriz::Vigente);
    $antes = $origen->fresh()->toArray();

    $this->actingAs($this->encargado)->postJson("/api/matrices/{$origen->id}/clonar", [
        'nombre' => 'Abril-Junio 2026',
        'fecha_revision' => '2026-06-30',
    ])->assertCreated()
        ->assertJsonPath('data.estado', 'borrador')
        ->assertJsonPath('data.proceso_id', $this->abastecimiento->id)
        ->assertJsonPath('data.edicion_anterior_id', $origen->id);

    expect($origen->fresh()->toArray())->toBe($antes);
});

test('archived editions can be used as a starting point', function () {
    $archivada = edicion($this->abastecimiento, 'Edición 3', EstadoMatriz::Archivada);

    $this->actingAs($this->encargado)->postJson("/api/matrices/{$archivada->id}/clonar", [
        'nombre' => 'Edición 4',
        'fecha_revision' => '2026-06-30',
    ])->assertCreated();
});

test('cloning validates the new edition name', function () {
    $origen = edicion($this->abastecimiento, 'Enero-Marzo 2026', EstadoMatriz::Vigente);

    $this->actingAs($this->encargado)->postJson("/api/matrices/{$origen->id}/clonar", [
        'nombre' => 'Enero-Marzo 2026',
        'fecha_revision' => '2026-06-30',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('nombre');
});

test('encargados can not clone editions of other departamentos', function () {
    $ajena = edicion($this->formacion, 'Enero-Marzo 2026', EstadoMatriz::Vigente);

    $this->actingAs($this->encargado)->postJson("/api/matrices/{$ajena->id}/clonar", [
        'nombre' => 'Copia',
        'fecha_revision' => '2026-06-30',
    ])->assertForbidden();
});

// --- Inmutabilidad en el modelo ---------------------------------------------

test('closed editions are immutable even outside the api', function (EstadoMatriz $estado) {
    $matriz = edicion($this->abastecimiento, 'Enero-Marzo 2026', $estado);

    expect(fn () => $matriz->update(['nombre' => 'Modificada por código']))
        ->toThrow(LogicException::class);

    expect($matriz->fresh()->nombre)->toBe('Enero-Marzo 2026');
})->with([EstadoMatriz::Vigente, EstadoMatriz::Archivada]);
