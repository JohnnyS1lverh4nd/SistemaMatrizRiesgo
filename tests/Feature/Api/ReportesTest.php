<?php

use App\Enums\EstadoMatriz;
use App\Models\FactorRiesgo;
use App\Models\NivelRiesgo;
use App\Models\Periodicidad;
use App\Models\Riesgo;
use App\Models\Seguimiento;
use App\Services\SeguimientoService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Carbon;
use Tests\Support\Escenario;

/*
 * Fase 8: dashboard, reporte de riesgos, atrasos de seguimiento y
 * comparación de ediciones (RF-5, RF-6).
 *
 * Decisiones (oct. 2026): el dashboard cuenta solo ediciones vigentes; todos
 * lo ven según su alcance; atrasado = sin seguimiento en los días de su
 * periodicidad (desde el último o desde la publicación) y tratamiento no
 * cumplido.
 */

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    Carbon::setTestNow('2026-10-15 10:00:00');

    $this->compras = Escenario::departamento('Compras', 'Administración y Finanzas');
    $this->admision = Escenario::departamento('Admisión', 'Direcciones Regionales');
    $this->abastecimiento = Escenario::proceso($this->compras, 'SP 4.3. Abastecimiento');
    $this->formacion = Escenario::proceso($this->admision, 'Formación Profesional');

    // Compras (vigente): Alto (4x3), Alto (3x4, pendiente), Bajo (2x2, cumplido).
    $this->vigenteCompras = Escenario::edicion($this->abastecimiento, 'Abril-Junio 2026', [
        ['p' => 4, 'i' => 3, 'descripcion' => 'Materiales no disponibles', 'factores' => ['administrativo'], 'responsable' => 'División de Compras', 'periodicidad' => 'trimestral'],
        ['p' => 3, 'i' => 4, 'descripcion' => 'Adjudicaciones erróneas', 'factores' => ['administrativo', 'personal'], 'responsable' => 'Comité de Compras', 'estado_tratamiento' => 'en_progreso'],
        ['p' => 2, 'i' => 2, 'descripcion' => 'Retraso en títulos', 'factores' => ['tecnologico'], 'estado_tratamiento' => 'cumplido', 'periodicidad' => 'mensual'],
    ], fecha: '2026-06-30');

    // Admisión (vigente): Crítico (4x4).
    $this->vigenteAdmision = Escenario::edicion($this->formacion, 'Enero-Marzo 2026', [
        ['p' => 4, 'i' => 4, 'descripcion' => 'Solicitudes fuera de perfil', 'factores' => ['administrativo']],
    ]);

    // Borrador y archivada: no cuentan en el dashboard.
    Escenario::edicion($this->abastecimiento, 'Borrador', [['p' => 4, 'i' => 4]], EstadoMatriz::Borrador);
    Escenario::edicion($this->abastecimiento, 'Edición 3', [['p' => 1, 'i' => 1]], EstadoMatriz::Archivada, '2022-02-28');

    $this->direccion = Escenario::usuario('direccion');
    $this->encargadoCompras = Escenario::usuario('encargado-departamento', $this->compras);
});

function nivelId(string $slug): int
{
    return NivelRiesgo::where('slug', $slug)->value('id');
}

// --- Periodicidad: frecuencia en días -------------------------------------------

test('periodicities carry their follow up frequency in days', function () {
    expect(Periodicidad::where('slug', 'trimestral')->value('frecuencia_dias'))->toBe(90)
        ->and(Periodicidad::where('slug', 'cuando-ocurra')->value('frecuencia_dias'))->toBeNull();

    $admin = Escenario::usuario('administrador');
    $mensual = Periodicidad::where('slug', 'mensual')->firstOrFail();

    $this->actingAs($admin)->putJson("/api/periodicidades/{$mensual->id}", ['frecuencia_dias' => 31])
        ->assertOk()
        ->assertJsonPath('data.frecuencia_dias', 31);
    $this->actingAs($admin)->putJson("/api/periodicidades/{$mensual->id}", ['frecuencia_dias' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('frecuencia_dias');
});

// --- Dashboard ----------------------------------------------------------------

test('dashboard counts only current editions, by effective level', function () {
    $datos = $this->actingAs($this->direccion)->getJson('/api/dashboard')->assertOk()->json('data');

    $porNivel = collect($datos['por_nivel'])->mapWithKeys(fn ($f) => [$f['nivel']['slug'] => $f['total']]);
    expect($datos['total_riesgos'])->toBe(4)
        ->and($porNivel->all())->toBe(['bajo' => 1, 'moderado' => 0, 'alto' => 2, 'critico' => 1])
        ->and($datos['por_estado_tratamiento'])->toBe(['pendiente' => 2, 'en_progreso' => 1, 'cumplido' => 1])
        // Alto o Crítico con tratamiento no cumplido.
        ->and($datos['prioritarios_pendientes'])->toBe(3);
});

test('dashboard heat map counts risks per probability and impact cell', function () {
    $mapa = collect($this->actingAs($this->direccion)->getJson('/api/dashboard')->json('data.mapa_calor'));

    expect($mapa)->toHaveCount(16);
    $celda = fn (int $p, int $i) => $mapa->first(fn ($c) => $c['probabilidad'] === $p && $c['impacto'] === $i);
    expect($celda(4, 3)['total'])->toBe(1)
        ->and($celda(4, 4)['total'])->toBe(1)
        ->and($celda(4, 4)['nivel']['slug'])->toBe('critico')
        ->and($celda(1, 1)['total'])->toBe(0);
});

test('a manual adjustment changes the effective level counted', function () {
    Escenario::clonar($this->vigenteCompras, 'Julio-Septiembre 2026', [2 => ['ajuste' => 'moderado']]);

    $porNivel = collect($this->actingAs($this->direccion)->getJson('/api/dashboard')->json('data.por_nivel'))
        ->mapWithKeys(fn ($f) => [$f['nivel']['slug'] => $f['total']]);

    expect($porNivel['alto'])->toBe(1)->and($porNivel['moderado'])->toBe(1);
});

test('dashboard is consolidated by departamento', function () {
    $porDepartamento = collect($this->actingAs($this->direccion)->getJson('/api/dashboard')->json('data.por_departamento'));

    $compras = $porDepartamento->firstWhere('departamento.id', $this->compras->id);
    expect($compras['departamento']['direccion'])->toBe('Administración y Finanzas')
        ->and($compras['total'])->toBe(3)
        ->and($compras['por_nivel'])->toMatchArray(['alto' => 2, 'bajo' => 1])
        ->and($compras['procesos_con_vigente'])->toBe(1)
        ->and($compras['procesos_sin_vigente'])->toBe(0);
});

test('encargados only see their departamentos in the dashboard', function () {
    $datos = $this->actingAs($this->encargadoCompras)->getJson('/api/dashboard')->assertOk()->json('data');

    expect($datos['total_riesgos'])->toBe(3)
        ->and(collect($datos['por_departamento'])->pluck('departamento.id')->all())->toBe([$this->compras->id]);

    // Filtrar por un departamento ajeno es un 403, no un resultado vacío.
    $this->actingAs($this->encargadoCompras)->getJson("/api/dashboard?departamento_id={$this->admision->id}")->assertForbidden();
});

test('dashboard can be filtered by departamento', function () {
    $this->actingAs($this->direccion)->getJson("/api/dashboard?departamento_id={$this->admision->id}")
        ->assertOk()
        ->assertJsonPath('data.total_riesgos', 1);
});

test('guests can not see the dashboard', function () {
    $this->getJson('/api/dashboard')->assertUnauthorized();
});

// --- Reporte de riesgos ---------------------------------------------------------

test('risk report lists current risks with their context', function () {
    $fila = collect($this->actingAs($this->direccion)->getJson('/api/reportes/riesgos')->assertOk()->json('data'))
        ->firstWhere('descripcion', 'Adjudicaciones erróneas');

    expect($fila['nivel']['slug'])->toBe('alto')
        ->and($fila['departamento']['nombre'])->toBe('Compras')
        ->and($fila['proceso']['nombre'])->toBe('SP 4.3. Abastecimiento')
        ->and($fila['matriz']['nombre'])->toBe('Abril-Junio 2026')
        ->and($fila['factores'])->toHaveCount(2);
});

test('risk report filters', function (callable $filtro, array $esperadas) {
    $query = http_build_query($filtro($this));

    $descripciones = collect($this->actingAs($this->direccion)->getJson("/api/reportes/riesgos?{$query}")->assertOk()->json('data'))
        ->pluck('descripcion')->sort()->values()->all();

    expect($descripciones)->toBe(collect($esperadas)->sort()->values()->all());
})->with([
    'departamento' => [fn ($t) => ['departamento_id' => $t->admision->id], ['Solicitudes fuera de perfil']],
    'proceso' => [fn ($t) => ['proceso_id' => $t->abastecimiento->id], ['Materiales no disponibles', 'Adjudicaciones erróneas', 'Retraso en títulos']],
    'factor' => [fn ($t) => ['factor_id' => FactorRiesgo::where('slug', 'personal')->value('id')], ['Adjudicaciones erróneas']],
    'nivel' => [fn ($t) => ['nivel_id' => nivelId('critico')], ['Solicitudes fuera de perfil']],
    'responsable (parcial, sin mayúsculas)' => [fn ($t) => ['responsable' => 'comité'], ['Adjudicaciones erróneas']],
    'estado del tratamiento' => [fn ($t) => ['estado_tratamiento' => 'cumplido'], ['Retraso en títulos']],
    'críticos y altos pendientes' => [fn ($t) => ['prioritarios' => 1], ['Materiales no disponibles', 'Adjudicaciones erróneas', 'Solicitudes fuera de perfil']],
]);

test('the period filter includes archived editions by review date, never drafts', function () {
    $descripciones = collect($this->actingAs($this->direccion)->getJson('/api/reportes/riesgos?desde=2022-01-01&hasta=2022-12-31')->json('data'))
        ->pluck('descripcion')->all();

    expect($descripciones)->toBe(['Riesgo 1']);

    $this->actingAs($this->direccion)->getJson('/api/reportes/riesgos?desde=2026-12-01&hasta=2026-01-01')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('hasta');
});

test('encargados only get risks of their departamentos in reports', function () {
    $departamentos = collect($this->actingAs($this->encargadoCompras)->getJson('/api/reportes/riesgos')->json('data'))
        ->pluck('departamento.id')->unique()->values()->all();

    expect($departamentos)->toBe([$this->compras->id]);
    $this->actingAs($this->encargadoCompras)->getJson("/api/reportes/riesgos?departamento_id={$this->admision->id}")->assertForbidden();
});

// --- Atrasos de seguimiento -----------------------------------------------------

test('a risk is overdue when no follow up was registered within its periodicity', function () {
    // Publicada hace más de 90 días; trimestral; sin seguimiento -> atrasado.
    $this->vigenteCompras->forceFill(['publicada_at' => '2026-07-01 08:00:00'])->saveQuietly();

    $filas = collect($this->actingAs($this->direccion)->getJson('/api/reportes/riesgos?atrasados=1')->assertOk()->json('data'));

    expect($filas->pluck('descripcion')->all())->toBe(['Materiales no disponibles'])
        ->and($filas->first()['seguimiento']['atrasado'])->toBeTrue()
        ->and($filas->first()['seguimiento']['vence'])->toBe('2026-09-29');
    expect($this->actingAs($this->direccion)->getJson('/api/dashboard')->json('data.seguimientos_atrasados'))->toBe(1);
});

test('a recent follow up resets the overdue clock, but voided entries do not count', function () {
    $this->vigenteCompras->forceFill(['publicada_at' => '2026-07-01 08:00:00'])->saveQuietly();
    $riesgo = Riesgo::where(['matriz_id' => $this->vigenteCompras->id, 'orden' => 1])->firstOrFail();
    $autor = Escenario::usuario('administrador');

    $valido = app(SeguimientoService::class)->registrar($riesgo, $autor, ['fecha' => '2026-10-01', 'descripcion' => 'Revisión trimestral']);

    $fila = collect($this->actingAs($this->direccion)->getJson('/api/reportes/riesgos')->json('data'))->firstWhere('id', $riesgo->id);
    expect($fila['seguimiento'])->toMatchArray(['ultimo' => '2026-10-01', 'vence' => '2026-12-30', 'atrasado' => false]);

    app(SeguimientoService::class)->anular(Seguimiento::findOrFail($valido->id), $autor, 'Registrado por error');

    $fila = collect($this->actingAs($this->direccion)->getJson('/api/reportes/riesgos')->json('data'))->firstWhere('id', $riesgo->id);
    expect($fila['seguimiento']['atrasado'])->toBeTrue();
});

test('risks without due date or already fulfilled are never overdue', function () {
    $this->vigenteCompras->forceFill(['publicada_at' => '2025-01-01 08:00:00'])->saveQuietly();

    $filas = collect($this->actingAs($this->direccion)->getJson('/api/reportes/riesgos')->json('data'))->keyBy('descripcion');

    // Sin periodicidad.
    expect($filas['Adjudicaciones erróneas']['seguimiento']['atrasado'])->toBeFalse()
        ->and($filas['Adjudicaciones erróneas']['seguimiento']['vence'])->toBeNull()
        // Tratamiento cumplido.
        ->and($filas['Retraso en títulos']['seguimiento']['atrasado'])->toBeFalse();
});

// --- Comparación de ediciones (RF-5) --------------------------------------------

test('comparing two editions shows added, closed and level changes', function () {
    // De "Abril-Junio" a "Julio-Septiembre": el riesgo 2 baja de Alto a Moderado,
    // el 3 se elimina (cerrado) y se agrega uno nuevo.
    $nueva = Escenario::clonar($this->vigenteCompras, 'Julio-Septiembre 2026',
        cambios: [2 => ['p' => 2, 'i' => 3], 3 => null],
        nuevos: [['p' => 3, 'i' => 3, 'descripcion' => 'Ciberataque a servidores']],
    );

    $resultado = $this->actingAs($this->encargadoCompras)
        ->getJson("/api/matrices/{$this->vigenteCompras->id}/comparar/{$nueva->id}")
        ->assertOk()
        ->json('data');

    expect(collect($resultado['agregados'])->pluck('descripcion')->all())->toBe(['Ciberataque a servidores'])
        ->and(collect($resultado['cerrados'])->pluck('descripcion')->all())->toBe(['Retraso en títulos'])
        ->and($resultado['cambios_nivel'])->toHaveCount(1)
        ->and($resultado['cambios_nivel'][0]['descripcion'])->toBe('Adjudicaciones erróneas')
        ->and($resultado['cambios_nivel'][0]['nivel_anterior']['slug'])->toBe('alto')
        ->and($resultado['cambios_nivel'][0]['nivel_nuevo']['slug'])->toBe('moderado')
        ->and($resultado['cambios_nivel'][0]['tendencia'])->toBe('baja')
        ->and($resultado['resumen'])->toBe(['agregados' => 1, 'cerrados' => 1, 'suben' => 0, 'bajan' => 1, 'sin_cambio' => 1]);
});

test('comparison follows the lineage across non consecutive editions', function () {
    $intermedia = Escenario::clonar($this->vigenteCompras, 'Julio-Septiembre 2026', [1 => ['p' => 4, 'i' => 4]]);
    $final = Escenario::clonar($intermedia, 'Octubre-Diciembre 2026', fecha: '2026-12-31');

    $cambios = $this->actingAs($this->direccion)
        ->getJson("/api/matrices/{$this->vigenteCompras->id}/comparar/{$final->id}")
        ->json('data.cambios_nivel');

    expect($cambios)->toHaveCount(1)
        ->and($cambios[0]['descripcion'])->toBe('Materiales no disponibles')
        ->and($cambios[0]['tendencia'])->toBe('sube');
});

test('only editions of the same proceso can be compared', function () {
    $this->actingAs($this->direccion)
        ->getJson("/api/matrices/{$this->vigenteCompras->id}/comparar/{$this->vigenteAdmision->id}")
        ->assertUnprocessable();
});

test('comparison respects the departamento scope', function () {
    $otra = Escenario::clonar($this->vigenteAdmision, 'Abril-Junio 2026');

    $this->actingAs($this->encargadoCompras)
        ->getJson("/api/matrices/{$this->vigenteAdmision->id}/comparar/{$otra->id}")
        ->assertForbidden();
});
