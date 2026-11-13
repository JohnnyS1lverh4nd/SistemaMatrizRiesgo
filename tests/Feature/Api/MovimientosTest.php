<?php

use App\Models\Auditoria;
use App\Models\Departamento;
use App\Models\Evidencia;
use App\Models\FactorRiesgo;
use App\Models\Matriz;
use App\Models\Proceso;
use App\Models\Riesgo;
use App\Models\Role;
use App\Models\Seguimiento;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
 * Historial de movimientos por área (nov. 2026). Reutiliza la auditoría
 * (RF-9): cada registro guarda además el departamento, proceso y edición
 * afectados. Lo consultan los Encargados (sus departamentos) y el
 * Administrador (todos). Auditor y Dirección no: el Auditor conserva Auditoría.
 */

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    Storage::fake('local');

    $this->compras = Departamento::create(['nombre' => 'Compras', 'slug' => 'compras']);
    $this->admision = Departamento::create(['nombre' => 'Admisión', 'slug' => 'admision']);
    $this->abastecimiento = Proceso::create(['departamento_id' => $this->compras->id, 'nombre' => 'Abastecimiento', 'slug' => 'abastecimiento']);
    $this->formacion = Proceso::create(['departamento_id' => $this->admision->id, 'nombre' => 'Formación', 'slug' => 'formacion']);

    $this->admin = conRol('administrador');
    $this->encargadoCompras = conRol('encargado-departamento', $this->compras);
    $this->colegaCompras = conRol('encargado-departamento', $this->compras);
    $this->seguimientoCompras = conRol('encargado-seguimiento', $this->compras);
    $this->encargadoAdmision = conRol('encargado-departamento', $this->admision);
});

function conRol(string $rol, ?Departamento $departamento = null): User
{
    $user = User::factory()->create(['role_id' => Role::where('slug', $rol)->value('id')]);
    if ($departamento) {
        $user->departamentos()->attach($departamento);
    }

    return $user;
}

/** Edición en borrador con un riesgo, creada por la API (como lo haría el usuario). */
function edicionConRiesgo(User $autor, Proceso $proceso, string $nombre = 'Edición 1'): array
{
    $matrizId = test()->actingAs($autor)
        ->postJson("/api/procesos/{$proceso->id}/matrices", ['nombre' => $nombre, 'fecha_revision' => '2026-06-30'])
        ->assertCreated()
        ->json('data.id');
    $riesgoId = test()->actingAs($autor)->postJson("/api/matrices/{$matrizId}/riesgos", [
        'actividad' => 'Evaluación de ofertas',
        'descripcion' => 'Adjudicaciones erróneas',
        'probabilidad' => 3,
        'impacto' => 4,
        'factor_ids' => [FactorRiesgo::where('slug', 'administrativo')->value('id')],
    ])->assertCreated()->json('data.id');

    return [Matriz::findOrFail($matrizId), Riesgo::findOrFail($riesgoId)];
}

// --- Contexto registrado ----------------------------------------------------

test('every movement on a matrix records its departamento, proceso and edition', function () {
    [$matriz, $riesgo] = edicionConRiesgo($this->encargadoCompras, $this->abastecimiento);

    $this->actingAs($this->encargadoCompras)->putJson("/api/riesgos/{$riesgo->id}", ['probabilidad' => 4])->assertOk();
    $this->actingAs($this->encargadoCompras)->postJson("/api/matrices/{$matriz->id}/publicar")->assertOk();
    $this->actingAs($this->seguimientoCompras)->post("/api/riesgos/{$riesgo->id}/seguimientos", [
        'fecha' => now()->toDateString(),
        'descripcion' => 'Revisión trimestral',
        'estado_tratamiento' => 'en_progreso',
        'archivos' => [UploadedFile::fake()->create('acta.pdf', 50, 'application/pdf')],
    ], ['Accept' => 'application/json'])->assertCreated();

    $seguimiento = Seguimiento::firstOrFail();
    $evidencia = Evidencia::firstOrFail();

    $registros = Auditoria::whereIn('entidad', ['matriz', 'riesgo', 'seguimiento', 'evidencia'])->get();
    expect($registros)->not->toBeEmpty();

    foreach ($registros as $registro) {
        expect([$registro->departamento_id, $registro->proceso_id, $registro->matriz_id])
            ->toBe([$this->compras->id, $this->abastecimiento->id, $matriz->id], "{$registro->entidad} {$registro->evento}");
    }

    // Incluye los cambios de factores (tabla pivote) y del estado del tratamiento.
    expect(Auditoria::where('entidad', 'riesgo')->whereNotNull('valores_nuevos->factor_ids')->exists())->toBeTrue()
        ->and(Auditoria::where('entidad', 'riesgo')->whereNotNull('valores_nuevos->estado_tratamiento')->exists())->toBeTrue()
        ->and(Auditoria::where(['entidad' => 'seguimiento', 'entidad_id' => $seguimiento->id])->exists())->toBeTrue()
        ->and(Auditoria::where(['entidad' => 'evidencia', 'entidad_id' => $evidencia->id])->exists())->toBeTrue();
});

test('creating and editing a proceso records its departamento and proceso', function () {
    $procesoId = $this->actingAs($this->encargadoCompras)
        ->postJson("/api/departamentos/{$this->compras->id}/procesos", ['nombre' => 'Almacén'])
        ->json('data.id');
    $this->actingAs($this->encargadoCompras)->putJson("/api/procesos/{$procesoId}", ['descripcion' => 'Nueva'])->assertOk();

    $registros = Auditoria::where(['entidad' => 'proceso', 'entidad_id' => $procesoId])->get();

    expect($registros)->toHaveCount(2);
    foreach ($registros as $registro) {
        expect([$registro->departamento_id, $registro->proceso_id, $registro->matriz_id])->toBe([$this->compras->id, $procesoId, null]);
    }
});

test('exports and discarded drafts keep their context', function () {
    [$matriz, $riesgo] = edicionConRiesgo($this->encargadoCompras, $this->abastecimiento);

    $this->actingAs($this->encargadoCompras)->deleteJson("/api/riesgos/{$riesgo->id}")->assertNoContent();
    $this->actingAs($this->encargadoCompras)->getJson("/api/matrices/{$matriz->id}/exportar/excel")->assertOk();
    $this->actingAs($this->encargadoCompras)->deleteJson("/api/matrices/{$matriz->id}")->assertNoContent();

    foreach (['eliminado', 'exportado'] as $evento) {
        foreach (Auditoria::where('evento', $evento)->get() as $registro) {
            expect($registro->matriz_id)->toBe($matriz->id)
                ->and($registro->departamento_id)->toBe($this->compras->id);
        }
    }
});

test('catalog, user and session events have no area', function () {
    $this->actingAs($this->admin)->putJson('/api/factores-riesgo/'.FactorRiesgo::firstOrFail()->id, ['nombre' => 'Renombrado'])->assertOk();

    expect(Auditoria::where('entidad', 'factor_riesgo')->latest('id')->firstOrFail()->departamento_id)->toBeNull();
});

// --- Quién lo consulta ------------------------------------------------------

test('encargados see the movements of everyone in their departamentos, and only those', function (string $quien) {
    edicionConRiesgo($this->encargadoCompras, $this->abastecimiento);
    edicionConRiesgo($this->colegaCompras, $this->abastecimiento, 'Edición 2');
    edicionConRiesgo($this->encargadoAdmision, $this->formacion);

    $respuesta = $this->actingAs($this->{$quien})->getJson('/api/movimientos')->assertOk();

    $departamentos = collect($respuesta->json('data'))->pluck('departamento.id')->unique()->values()->all();
    // Sin los registros sin usuario (el beforeEach crea departamento y procesos sin sesión).
    $usuarios = collect($respuesta->json('data'))->pluck('usuario.id')->filter()->unique()->sort()->values()->all();

    expect($departamentos)->toBe([$this->compras->id])
        ->and($usuarios)->toBe(collect([$this->encargadoCompras->id, $this->colegaCompras->id])->sort()->values()->all());
})->with(['encargadoCompras', 'seguimientoCompras']);

test('the admin sees the movements of every departamento', function () {
    edicionConRiesgo($this->encargadoCompras, $this->abastecimiento);
    edicionConRiesgo($this->encargadoAdmision, $this->formacion);

    $departamentos = collect($this->actingAs($this->admin)->getJson('/api/movimientos?per_page=100')->assertOk()->json('data'))
        ->pluck('departamento.id')->unique()->sort()->values()->all();

    expect($departamentos)->toBe(collect([$this->compras->id, $this->admision->id])->sort()->values()->all());
});

test('auditor and direccion can not use the area history', function (string $rol) {
    $this->actingAs(conRol($rol))->getJson('/api/movimientos')->assertForbidden();
})->with(['auditor', 'direccion']);

test('guests can not see the area history', function () {
    $this->getJson('/api/movimientos')->assertUnauthorized();
});

test('filtering by another departamento, proceso or edition never leaks its movements', function () {
    [$ajena] = edicionConRiesgo($this->encargadoAdmision, $this->formacion);

    foreach ([
        "departamento_id={$this->admision->id}",
        "proceso_id={$this->formacion->id}",
        "matriz_id={$ajena->id}",
    ] as $filtro) {
        $this->actingAs($this->encargadoCompras)->getJson("/api/movimientos?{$filtro}")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
});

test('the detail of a movement from another departamento is forbidden', function () {
    edicionConRiesgo($this->encargadoAdmision, $this->formacion);
    $ajeno = Auditoria::where('departamento_id', $this->admision->id)->firstOrFail();

    $this->actingAs($this->encargadoCompras)->getJson("/api/movimientos/{$ajeno->id}")->assertForbidden();
    $this->actingAs($this->encargadoAdmision)->getJson("/api/movimientos/{$ajeno->id}")->assertOk();
});

test('events without an area are not part of the area history, not even for the admin', function () {
    $this->actingAs($this->admin)->putJson('/api/factores-riesgo/'.FactorRiesgo::firstOrFail()->id, ['nombre' => 'Renombrado'])->assertOk();
    $catalogo = Auditoria::where('entidad', 'factor_riesgo')->latest('id')->firstOrFail();

    $this->actingAs($this->admin)->getJson("/api/movimientos/{$catalogo->id}")->assertNotFound();
    expect(collect($this->actingAs($this->admin)->getJson('/api/movimientos')->json('data'))->pluck('entidad'))
        ->not->toContain('factor_riesgo');
});

test('an encargado unassigned from a departamento stops seeing its history', function () {
    edicionConRiesgo($this->encargadoCompras, $this->abastecimiento);
    $this->encargadoCompras->departamentos()->detach($this->compras);

    $this->actingAs($this->encargadoCompras->fresh())->getJson('/api/movimientos')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

// --- Filtros, detalle y opciones -------------------------------------------

test('movements can be filtered by user, operation, proceso, edition and dates', function () {
    [$primera] = edicionConRiesgo($this->encargadoCompras, $this->abastecimiento);
    [$segunda] = edicionConRiesgo($this->colegaCompras, $this->abastecimiento, 'Edición 2');
    $otroProceso = Proceso::create(['departamento_id' => $this->compras->id, 'nombre' => 'Almacén', 'slug' => 'almacen']);
    edicionConRiesgo($this->colegaCompras, $otroProceso);

    $consultar = fn (string $query) => collect($this->actingAs($this->encargadoCompras)->getJson("/api/movimientos?{$query}")->assertOk()->json('data'));

    expect($consultar("usuario_id={$this->colegaCompras->id}")->pluck('usuario.id')->unique()->all())->toBe([$this->colegaCompras->id])
        ->and($consultar('evento=creado')->pluck('evento')->unique()->all())->toBe(['creado'])
        ->and($consultar("proceso_id={$otroProceso->id}")->pluck('proceso.id')->unique()->all())->toBe([$otroProceso->id])
        ->and($consultar("matriz_id={$segunda->id}")->pluck('matriz.id')->unique()->all())->toBe([$segunda->id])
        ->and($consultar('entidad=riesgo')->pluck('entidad')->unique()->all())->toBe(['riesgo'])
        ->and($consultar('desde='.now()->addDay()->toDateString()))->toBeEmpty()
        ->and($consultar('hasta='.now()->toDateString()))->not->toBeEmpty();

    $this->actingAs($this->encargadoCompras)->getJson('/api/movimientos?desde=2026-06-30&hasta=2026-06-01')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('hasta');
    expect($primera)->not->toBeNull();
});

test('a movement shows names, previous and new values, but no ip or browser', function () {
    [, $riesgo] = edicionConRiesgo($this->encargadoCompras, $this->abastecimiento);
    $this->actingAs($this->encargadoCompras)->putJson("/api/riesgos/{$riesgo->id}", ['probabilidad' => 4])->assertOk();
    $registro = Auditoria::where(['entidad' => 'riesgo', 'evento' => 'actualizado'])->whereNotNull('valores_nuevos->probabilidad')->firstOrFail();

    $this->actingAs($this->colegaCompras)->getJson("/api/movimientos/{$registro->id}")
        ->assertOk()
        ->assertJsonPath('data.usuario.name', $this->encargadoCompras->name)
        ->assertJsonPath('data.departamento.nombre', 'Compras')
        ->assertJsonPath('data.proceso.nombre', 'Abastecimiento')
        ->assertJsonPath('data.matriz.nombre', 'Edición 1')
        ->assertJsonPath('data.entidad', 'riesgo')
        ->assertJsonPath('data.entidad_id', $riesgo->id)
        ->assertJsonPath('data.valores_anteriores.probabilidad', 3)
        ->assertJsonPath('data.valores_nuevos.probabilidad', 4)
        ->assertJsonMissingPath('data.ip')
        ->assertJsonMissingPath('data.user_agent');
});

test('discarded editions still show their name in the history', function () {
    [$matriz] = edicionConRiesgo($this->encargadoCompras, $this->abastecimiento);
    $this->actingAs($this->encargadoCompras)->deleteJson("/api/matrices/{$matriz->id}")->assertNoContent();

    $this->actingAs($this->encargadoCompras)->getJson('/api/movimientos?evento=eliminado&entidad=matriz')
        ->assertOk()
        ->assertJsonPath('data.0.matriz.nombre', 'Edición 1');
});

test('filter options only list users, procesos and editions within reach', function () {
    [$propia] = edicionConRiesgo($this->encargadoCompras, $this->abastecimiento);
    [$ajena] = edicionConRiesgo($this->encargadoAdmision, $this->formacion);

    $opciones = $this->actingAs($this->encargadoCompras)->getJson('/api/movimientos/opciones')->assertOk();

    expect(collect($opciones->json('data.usuarios'))->pluck('id')->all())->toBe([$this->encargadoCompras->id])
        ->and(collect($opciones->json('data.departamentos'))->pluck('id')->all())->toBe([$this->compras->id])
        ->and(collect($opciones->json('data.procesos'))->pluck('id')->all())->toBe([$this->abastecimiento->id])
        ->and(collect($opciones->json('data.matrices'))->pluck('id')->all())->toBe([$propia->id])
        ->and(collect($opciones->json('data.matrices'))->pluck('id'))->not->toContain($ajena->id);

    $this->actingAs(conRol('auditor'))->getJson('/api/movimientos/opciones')->assertForbidden();
});

// --- Registros anteriores a nov. 2026 (migración de completado) ------------

test('the backfill migration fills the area of old records and changes nothing else', function () {
    [$matriz, $riesgo] = edicionConRiesgo($this->encargadoCompras, $this->abastecimiento);
    $this->actingAs($this->encargadoCompras)->postJson("/api/matrices/{$matriz->id}/publicar")->assertOk();
    $this->actingAs($this->encargadoCompras)->post("/api/riesgos/{$riesgo->id}/seguimientos", [
        'fecha' => now()->toDateString(),
        'descripcion' => 'Revisión',
        'archivos' => [UploadedFile::fake()->create('acta.pdf', 50, 'application/pdf')],
    ], ['Accept' => 'application/json'])->assertCreated();
    $this->actingAs($this->admin)->putJson('/api/factores-riesgo/'.FactorRiesgo::firstOrFail()->id, ['nombre' => 'Renombrado'])->assertOk();

    // Simula registros escritos antes de existir las columnas de área.
    DB::table('auditoria')->update(['departamento_id' => null, 'proceso_id' => null, 'matriz_id' => null]);
    $antes = DB::table('auditoria')->orderBy('id')->get()->map(fn ($r) => collect((array) $r)->except(['departamento_id', 'proceso_id', 'matriz_id'])->all());

    (require database_path('migrations/2026_11_12_200100_backfill_area_in_auditoria_table.php'))->up();

    foreach (['proceso', 'matriz', 'riesgo', 'seguimiento', 'evidencia'] as $entidad) {
        $registros = Auditoria::where('entidad', $entidad)->where('departamento_id', $this->compras->id)->get();
        expect($registros)->not->toBeEmpty("{$entidad} sin área");
    }
    expect(Auditoria::where('entidad', 'riesgo')->pluck('matriz_id')->unique()->values()->all())->toBe([$matriz->id])
        ->and(Auditoria::where('entidad', 'factor_riesgo')->whereNotNull('departamento_id')->exists())->toBeFalse()
        ->and(Auditoria::where('entidad', 'usuario')->whereNotNull('departamento_id')->exists())->toBeFalse();

    // Evento, usuario, fecha y valores: intactos.
    $despues = DB::table('auditoria')->orderBy('id')->get()->map(fn ($r) => collect((array) $r)->except(['departamento_id', 'proceso_id', 'matriz_id'])->all());
    expect($despues->all())->toBe($antes->all());
});

// --- Solo lectura -----------------------------------------------------------

test('movements can not be created, changed or deleted through the api', function () {
    edicionConRiesgo($this->encargadoCompras, $this->abastecimiento);
    $registro = Auditoria::where('departamento_id', $this->compras->id)->firstOrFail();

    $this->actingAs($this->admin)->postJson('/api/movimientos', ['evento' => 'creado'])->assertMethodNotAllowed();
    $this->actingAs($this->admin)->putJson("/api/movimientos/{$registro->id}", ['evento' => 'x'])->assertMethodNotAllowed();
    $this->actingAs($this->admin)->deleteJson("/api/movimientos/{$registro->id}")->assertMethodNotAllowed();

    expect($registro->fresh()->evento)->toBe($registro->evento);
});

test('the area context of an audit record can not be changed by the application', function () {
    edicionConRiesgo($this->encargadoCompras, $this->abastecimiento);
    $registro = Auditoria::where('departamento_id', $this->compras->id)->firstOrFail();

    expect(fn () => $registro->forceFill(['departamento_id' => $this->admision->id])->save())->toThrow(LogicException::class);
});
