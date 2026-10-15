<?php

use App\Enums\EstadoMatriz;
use App\Models\Departamento;
use App\Models\FactorRiesgo;
use App\Models\Matriz;
use App\Models\MatrizCalorCelda;
use App\Models\NivelRiesgo;
use App\Models\Periodicidad;
use App\Models\Proceso;
use App\Models\Riesgo;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

/*
 * Fase 4: riesgos de una edición de matriz (13 campos del Excel, RF-3).
 *
 * Decisiones (oct. 2026): control actual, tratamiento, responsable y
 * periodicidad son campos del riesgo; responsable es texto libre; el nivel
 * se calcula con la matriz de calor y quien edita puede ajustarlo con
 * justificación. Los riesgos solo cambian mientras la edición es borrador.
 */

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->compras = Departamento::create(['nombre' => 'Compras', 'slug' => 'compras']);
    $this->admision = Departamento::create(['nombre' => 'Admisión', 'slug' => 'admision']);
    $this->proceso = Proceso::create(['departamento_id' => $this->compras->id, 'nombre' => 'SP 4.3. Abastecimiento', 'slug' => 'abastecimiento']);
    $this->procesoAjeno = Proceso::create(['departamento_id' => $this->admision->id, 'nombre' => 'Formación', 'slug' => 'formacion']);

    $this->borrador = matrizEn($this->proceso, 'Abril-Junio 2026');

    $this->encargado = User::factory()->create(['role_id' => Role::where('slug', 'encargado-departamento')->value('id')]);
    $this->encargado->departamentos()->attach($this->compras);
    $this->admin = User::factory()->create(['role_id' => Role::where('slug', 'administrador')->value('id')]);

    $this->administrativo = FactorRiesgo::where('slug', 'administrativo')->firstOrFail();
    $this->tecnologico = FactorRiesgo::where('slug', 'tecnologico')->firstOrFail();
    $this->trimestral = Periodicidad::where('slug', 'trimestral')->firstOrFail();
});

function matrizEn(Proceso $proceso, string $nombre, EstadoMatriz $estado = EstadoMatriz::Borrador): Matriz
{
    $matriz = (new Matriz)->forceFill([
        'proceso_id' => $proceso->id,
        'nombre' => $nombre,
        'fecha_revision' => '2026-06-30',
        'estado' => $estado,
    ]);
    $matriz->save();

    return $matriz;
}

/** Fila real de la matriz de Compras (Excel, fila 7). */
function riesgoValido(array $cambios = []): array
{
    return [
        'actividad' => 'Evaluación de ofertas de los oferentes',
        'descripcion' => 'Adjudicaciones erróneas',
        'factor_ids' => [FactorRiesgo::where('slug', 'administrativo')->value('id')],
        'causas' => 'Criterios de evaluación poco claros en el pliego de condiciones.',
        'control_actual' => 'Pautas a los peritos previa evaluación.',
        'probabilidad' => 3,
        'impacto' => 4,
        'sintoma' => 'Reclamaciones de los suplidores.',
        'tratamiento' => 'Revisión de los pliegos antes de cada publicación.',
        'responsable' => '*Departamento de Compras. *Comité de Compras.',
        'periodicidad_id' => Periodicidad::where('slug', 'trimestral')->value('id'),
        ...$cambios,
    ];
}

function crearRiesgo(Matriz $matriz, array $cambios = []): Riesgo
{
    $id = test()->actingAs(test()->admin)
        ->postJson("/api/matrices/{$matriz->id}/riesgos", riesgoValido($cambios))
        ->assertCreated()
        ->json('data.id');

    return Riesgo::findOrFail($id);
}

// --- Alta y nivel calculado -------------------------------------------------

test('encargado can register a risk with the thirteen excel fields', function () {
    $this->actingAs($this->encargado)->postJson("/api/matrices/{$this->borrador->id}/riesgos", riesgoValido([
        'factor_ids' => [$this->administrativo->id, $this->tecnologico->id],
    ]))->assertCreated()
        ->assertJsonPath('data.matriz_id', $this->borrador->id)
        ->assertJsonPath('data.actividad', 'Evaluación de ofertas de los oferentes')
        ->assertJsonPath('data.descripcion', 'Adjudicaciones erróneas')
        ->assertJsonPath('data.probabilidad', 3)
        ->assertJsonPath('data.impacto', 4)
        ->assertJsonPath('data.nivel_calculado.slug', 'alto')
        ->assertJsonPath('data.nivel.slug', 'alto')
        ->assertJsonPath('data.nivel_ajustado', null)
        ->assertJsonPath('data.periodicidad.id', $this->trimestral->id)
        ->assertJsonPath('data.responsable', '*Departamento de Compras. *Comité de Compras.')
        ->assertJsonPath('data.estado_tratamiento', 'pendiente')
        ->assertJsonPath('data.orden', 1)
        ->assertJsonCount(2, 'data.factores');
});

test('risks are numbered in the order they are registered', function () {
    crearRiesgo($this->borrador);
    crearRiesgo($this->borrador, ['descripcion' => 'Segundo']);

    $this->actingAs($this->encargado)->getJson("/api/matrices/{$this->borrador->id}/riesgos")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.orden', 1)
        ->assertJsonPath('data.1.orden', 2)
        ->assertJsonPath('data.1.descripcion', 'Segundo');
});

test('a risk can be saved without evaluation while the edition is a draft', function () {
    $this->actingAs($this->encargado)->postJson("/api/matrices/{$this->borrador->id}/riesgos", riesgoValido([
        'probabilidad' => null,
        'impacto' => null,
    ]))->assertCreated()
        ->assertJsonPath('data.nivel', null)
        ->assertJsonPath('data.nivel_calculado', null);
});

test('risk creation validates input', function (array $cambios, string $campo) {
    $this->actingAs($this->encargado)->postJson("/api/matrices/{$this->borrador->id}/riesgos", riesgoValido($cambios))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($campo);
})->with([
    'sin descripción' => [['descripcion' => ''], 'descripcion'],
    'sin actividad' => [['actividad' => ''], 'actividad'],
    'probabilidad fuera de escala' => [['probabilidad' => 5], 'probabilidad'],
    'impacto fuera de escala' => [['impacto' => 0], 'impacto'],
    'probabilidad sin impacto' => [['impacto' => null], 'impacto'],
    'impacto sin probabilidad' => [['probabilidad' => null], 'probabilidad'],
    'factor inexistente' => [['factor_ids' => [9999]], 'factor_ids.0'],
    'periodicidad inexistente' => [['periodicidad_id' => 9999], 'periodicidad_id'],
    'estado de tratamiento inválido' => [['estado_tratamiento' => 'terminado'], 'estado_tratamiento'],
    'nivel calculado inyectado' => [['nivel_calculado_id' => 1], 'nivel_calculado_id'],
]);

test('inactive factors and periodicities can not be chosen for new risks', function () {
    $this->administrativo->update(['activo' => false]);

    $this->actingAs($this->encargado)->postJson("/api/matrices/{$this->borrador->id}/riesgos", riesgoValido())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('factor_ids.0');
});

// --- Ajuste manual justificado (RF-3) ---------------------------------------

test('the level can be manually adjusted with a justification', function () {
    $moderado = NivelRiesgo::where('slug', 'moderado')->firstOrFail();

    $this->actingAs($this->encargado)->postJson("/api/matrices/{$this->borrador->id}/riesgos", riesgoValido([
        'nivel_ajustado_id' => $moderado->id,
        'justificacion_ajuste' => 'Controles compensatorios del Comité de Compras.',
    ]))->assertCreated()
        ->assertJsonPath('data.nivel_calculado.slug', 'alto')
        ->assertJsonPath('data.nivel_ajustado.slug', 'moderado')
        ->assertJsonPath('data.nivel.slug', 'moderado')
        ->assertJsonPath('data.justificacion_ajuste', 'Controles compensatorios del Comité de Compras.');
});

test('manual adjustment rules', function (array $cambios, string $campo, string $mensaje) {
    // Niveles por slug, no por id: en MariaDB los AUTO_INCREMENT no se
    // reinician entre pruebas (el rollback no los deshace).
    if (isset($cambios['nivel_ajustado_id'])) {
        $cambios['nivel_ajustado_id'] = NivelRiesgo::where('slug', $cambios['nivel_ajustado_id'])->value('id');
    }

    // El mensaje asegura que falla por la regla que se prueba y no por otra.
    $this->actingAs($this->encargado)->postJson("/api/matrices/{$this->borrador->id}/riesgos", riesgoValido($cambios))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$campo => $mensaje]);
})->with([
    'ajuste sin justificación' => [['nivel_ajustado_id' => 'moderado', 'justificacion_ajuste' => ''], 'justificacion_ajuste', 'Justifique el ajuste manual'],
    'ajuste igual al calculado' => [['nivel_ajustado_id' => 'alto', 'justificacion_ajuste' => 'Igual'], 'nivel_ajustado_id', 'coincide con el calculado'],
    'ajuste de un riesgo sin evaluar' => [['probabilidad' => null, 'impacto' => null, 'nivel_ajustado_id' => 'moderado', 'justificacion_ajuste' => 'X'], 'nivel_ajustado_id', 'Evalúe la probabilidad y el impacto'],
    'justificación sin ajuste' => [['justificacion_ajuste' => 'Sin ajuste'], 'justificacion_ajuste', 'solo aplica cuando se ajusta'],
]);

// --- Edición ----------------------------------------------------------------

test('updating probability and impact recalculates the level', function () {
    $riesgo = crearRiesgo($this->borrador);

    $this->actingAs($this->encargado)->putJson("/api/riesgos/{$riesgo->id}", ['probabilidad' => 1, 'impacto' => 1])
        ->assertOk()
        ->assertJsonPath('data.nivel_calculado.slug', 'bajo');
});

test('factors are replaced when sent', function () {
    $riesgo = crearRiesgo($this->borrador);

    $this->actingAs($this->encargado)->putJson("/api/riesgos/{$riesgo->id}", ['factor_ids' => [$this->tecnologico->id]])
        ->assertOk()
        ->assertJsonCount(1, 'data.factores')
        ->assertJsonPath('data.factores.0.id', $this->tecnologico->id);
});

test('the stored level does not change when the heat map changes later', function () {
    $riesgo = crearRiesgo($this->borrador);

    MatrizCalorCelda::where(['probabilidad' => 3, 'impacto' => 4])
        ->update(['nivel_riesgo_id' => NivelRiesgo::where('slug', 'critico')->value('id')]);

    $this->actingAs($this->encargado)->getJson("/api/riesgos/{$riesgo->id}")
        ->assertJsonPath('data.nivel_calculado.slug', 'alto');
});

test('risks of published or archived editions can not be changed', function (EstadoMatriz $estado) {
    $riesgo = crearRiesgo($this->borrador);
    $this->borrador->forceFill(['estado' => EstadoMatriz::Vigente])->saveQuietly();
    if ($estado === EstadoMatriz::Archivada) {
        $this->borrador->forceFill(['estado' => EstadoMatriz::Archivada])->saveQuietly();
    }

    $this->actingAs($this->admin)->putJson("/api/riesgos/{$riesgo->id}", ['descripcion' => 'Cambio'])->assertForbidden();
    $this->actingAs($this->admin)->deleteJson("/api/riesgos/{$riesgo->id}")->assertForbidden();
    $this->actingAs($this->admin)->postJson("/api/matrices/{$this->borrador->id}/riesgos", riesgoValido())->assertForbidden();

    expect($riesgo->fresh()->descripcion)->toBe('Adjudicaciones erróneas');
    expect(fn () => $riesgo->fresh()->update(['descripcion' => 'Por código']))->toThrow(LogicException::class);
})->with([EstadoMatriz::Vigente, EstadoMatriz::Archivada]);

// --- Eliminación lógica -----------------------------------------------------

test('a risk of a draft is removed with soft delete', function () {
    $riesgo = crearRiesgo($this->borrador);

    $this->actingAs($this->encargado)->deleteJson("/api/riesgos/{$riesgo->id}")->assertNoContent();

    $this->assertSoftDeleted($riesgo);
    $this->actingAs($this->encargado)->getJson("/api/matrices/{$this->borrador->id}/riesgos")->assertJsonCount(0, 'data');
});

// --- Alcance y roles --------------------------------------------------------

test('encargados can not see or change risks of other departamentos', function () {
    $ajena = matrizEn($this->procesoAjeno, 'Ajena');
    $riesgo = crearRiesgo($ajena);

    $this->actingAs($this->encargado)->getJson("/api/matrices/{$ajena->id}/riesgos")->assertForbidden();
    $this->actingAs($this->encargado)->getJson("/api/riesgos/{$riesgo->id}")->assertForbidden();
    $this->actingAs($this->encargado)->putJson("/api/riesgos/{$riesgo->id}", ['descripcion' => 'X'])->assertForbidden();
    $this->actingAs($this->encargado)->postJson("/api/matrices/{$ajena->id}/riesgos", riesgoValido())->assertForbidden();
});

test('read only roles can see risks but not change them', function (string $rol) {
    $user = User::factory()->create(['role_id' => Role::where('slug', $rol)->value('id')]);
    $riesgo = crearRiesgo($this->borrador);

    $this->actingAs($user)->getJson("/api/riesgos/{$riesgo->id}")->assertOk()->assertJsonPath('data.permisos.editar', false);
    $this->actingAs($user)->putJson("/api/riesgos/{$riesgo->id}", ['descripcion' => 'X'])->assertForbidden();
})->with(['auditor', 'direccion']);

// --- Publicar exige riesgos evaluados ---------------------------------------

test('an edition with unevaluated risks can not be published', function () {
    crearRiesgo($this->borrador, ['probabilidad' => null, 'impacto' => null]);

    $this->actingAs($this->encargado)->postJson("/api/matrices/{$this->borrador->id}/publicar")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('riesgos');

    expect($this->borrador->fresh()->estado)->toBe(EstadoMatriz::Borrador);
});

// --- Clonado de ediciones copia los riesgos ---------------------------------

test('cloning an edition copies its risks linked to the originals', function () {
    $moderado = NivelRiesgo::where('slug', 'moderado')->firstOrFail();
    $original = crearRiesgo($this->borrador, [
        'factor_ids' => [$this->administrativo->id, $this->tecnologico->id],
        'nivel_ajustado_id' => $moderado->id,
        'justificacion_ajuste' => 'Controles compensatorios.',
    ]);
    $descartado = crearRiesgo($this->borrador, ['descripcion' => 'Descartado']);
    $descartado->delete();

    $respuesta = $this->actingAs($this->encargado)->postJson("/api/matrices/{$this->borrador->id}/clonar", [
        'nombre' => 'Julio-Septiembre 2026',
        'fecha_revision' => '2026-09-30',
    ])->assertCreated();

    $copias = Riesgo::where('matriz_id', $respuesta->json('data.id'))->get();
    expect($copias)->toHaveCount(1);

    $copia = $copias->first();
    expect($copia->riesgo_origen_id)->toBe($original->id)
        ->and($copia->descripcion)->toBe($original->descripcion)
        ->and($copia->probabilidad)->toBe(3)
        ->and($copia->nivel_ajustado_id)->toBe($moderado->id)
        ->and($copia->justificacion_ajuste)->toBe('Controles compensatorios.')
        ->and($copia->factores->pluck('id')->sort()->values()->all())
        ->toBe(collect([$this->administrativo->id, $this->tecnologico->id])->sort()->values()->all());

    // El original sigue intacto en su edición.
    expect($original->fresh()->matriz_id)->toBe($this->borrador->id);
});
