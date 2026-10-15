<?php

use App\Enums\EstadoMatriz;
use App\Models\Auditoria;
use App\Models\FactorRiesgo;
use App\Models\Matriz;
use App\Models\NivelRiesgo;
use App\Models\Periodicidad;
use App\Models\Riesgo;
use App\Models\Seguimiento;
use App\Services\Importacion\ImportadorMatrices;
use App\Services\Reportes\ComparadorEdiciones;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\Support\Escenario;
use Tests\Support\LibroExcel;

/*
 * Fase 9 (RF-7): importación de las matrices de los Excel institucionales.
 * analizar() no escribe nada; ejecutar() crea las ediciones con los
 * servicios del dominio, en una sola transacción.
 */

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $compras = Escenario::departamento('Compras', 'Administración y Finanzas');
    $this->proceso = Escenario::proceso($compras, 'SP 4.3. Abastecimiento');
    $this->admin = Escenario::usuario('administrador');
    Auth::setUser($this->admin);

    // Como el Excel de Compras: tres hojas, una por edición, fuera de orden cronológico.
    $this->compras = LibroExcel::guardar(function (Spreadsheet $libro) {
        LibroExcel::hoja($libro->getActiveSheet()->setTitle('ENERO-MARZO 2026'), 'FECHA DE REVISIÓN: MARZO 2026', [
            LibroExcel::riesgo(['descripcion' => 'Materiales no disponibles', 'probabilidad' => 4, 'impacto' => 3, 'seguimiento' => 'Verificación diaria de requisiciones.']),
            LibroExcel::riesgo(),
        ]);
        LibroExcel::hoja($libro->createSheet()->setTitle('Matriz de Riesgos_Edición 3'), 'FECHA DE REVISIÓN: Febrero 2022', [
            LibroExcel::riesgo(['descripcion' => 'Materiales no disponibles', 'probabilidad' => 4, 'impacto' => 3]),
            LibroExcel::riesgo(),
        ]);
        LibroExcel::hoja($libro->createSheet()->setTitle('ABRIL-JUNIO 2026'), 'FECHA DE REVISIÓN: JUNIO 2026', [
            LibroExcel::riesgo(['descripcion' => 'Materiales no disponibles', 'probabilidad' => 4, 'impacto' => 3]),
            LibroExcel::riesgo(['probabilidad' => 2, 'impacto' => 2, 'nivel' => 'Bajo']),
            LibroExcel::riesgo(['descripcion' => 'Riesgo nuevo en junio', 'probabilidad' => 1, 'impacto' => 1, 'nivel' => 'Bajo']),
        ]);
    });
});

afterEach(fn () => LibroExcel::limpiar());

function importador(): ImportadorMatrices
{
    return app(ImportadorMatrices::class);
}

/** @param  array<int, array<int, mixed>>  $filas */
function libroDeUnaHoja(array $filas, string $fecha = 'Fecha de Revisión: Junio 2026', string $hoja = 'Matriz de Riesgos_Edición 3'): string
{
    return LibroExcel::guardar(fn (Spreadsheet $l) => LibroExcel::hoja($l->getActiveSheet()->setTitle($hoja), $fecha, $filas));
}

// --- Análisis (no escribe nada) ----------------------------------------------

test('each visible sheet becomes an edition, in chronological order', function () {
    $plan = importador()->analizar($this->compras, $this->proceso);

    expect($plan->errores)->toBe([])
        ->and(collect($plan->ediciones)->pluck('nombre')->all())->toBe(['Edición 3', 'Enero-Marzo 2026', 'Abril-Junio 2026'])
        ->and(collect($plan->ediciones)->pluck('fecha_revision')->all())->toBe(['2022-02-28', '2026-03-31', '2026-06-30'])
        ->and(collect($plan->ediciones)->map(fn ($e) => count($e['riesgos']))->all())->toBe([2, 2, 3])
        ->and(Matriz::count())->toBe(0);
});

test('risk rows are translated to catalogue ids', function () {
    $riesgo = importador()->analizar($this->compras, $this->proceso)->ediciones[0]['riesgos'][1];

    expect($riesgo['datos'])->toMatchArray([
        'actividad' => 'Evaluación de ofertas',
        'descripcion' => 'Adjudicaciones erróneas',
        'causas' => 'Criterios ambiguos',
        'probabilidad' => 3,
        'impacto' => 4,
        'factor_ids' => [FactorRiesgo::where('slug', 'administrativo')->value('id')],
        // "Revisión cada tres meses" es un alias configurado de Trimestral.
        'periodicidad_id' => Periodicidad::where('slug', 'trimestral')->value('id'),
        'nivel_ajustado_id' => null,
        'justificacion_ajuste' => null,
    ])->and($riesgo['fila'])->toBe(7);
});

test('combined factors map to several catalogue entries', function () {
    $plan = importador()->analizar(libroDeUnaHoja([LibroExcel::riesgo(['factor' => 'Pnal / Tec'])]), $this->proceso);

    expect($plan->errores)->toBe([])
        ->and($plan->ediciones[0]['riesgos'][0]['datos']['factor_ids'])->toBe(
            FactorRiesgo::whereIn('slug', ['personal', 'tecnologico'])->orderBy('id')->pluck('id')->all()
        );
});

test('a level that differs from the heat map is kept as a justified manual adjustment', function () {
    // Como SFP: P = 3, I = 4 clasificado Moderado (la regla da Alto).
    $plan = importador()->analizar(libroDeUnaHoja([LibroExcel::riesgo(['nivel' => 'Moderado'])]), $this->proceso);

    expect($plan->ediciones[0]['riesgos'][0]['datos'])->toMatchArray([
        'nivel_ajustado_id' => NivelRiesgo::where('slug', 'moderado')->value('id'),
        'justificacion_ajuste' => 'Nivel asignado en el Excel original (importación).',
    ])->and($plan->avisos)->toContain('Hoja "Matriz de Riesgos_Edición 3", fila 6: el Excel indica Moderado y la matriz de calor calcula Alto; se importa como ajuste manual.');
});

test('a sheet named like a generic sheet takes its name from the review date', function () {
    $plan = importador()->analizar(libroDeUnaHoja([LibroExcel::riesgo()], 'Fecha de Revisión: 30 Abril 2025', 'Hoja1'), $this->proceso);

    expect($plan->ediciones[0]['nombre'])->toBe('Abril 2025')
        ->and($plan->ediciones[0]['fecha_revision'])->toBe('2025-04-30');
});

test('problems are reported with sheet and row, and the plan is not valid', function () {
    $plan = importador()->analizar(libroDeUnaHoja([
        LibroExcel::riesgo(['periodicidad' => 'Cada luna llena']),
        LibroExcel::riesgo(['factor' => 'Desconocido']),
        LibroExcel::riesgo(['probabilidad' => null, 'impacto' => null, 'nivel' => null]),
        LibroExcel::riesgo(['impacto' => 7]),
        LibroExcel::riesgo(['descripcion' => null]),
        LibroExcel::riesgo(['nivel' => 'Altísimo']),
    ], 'Fecha de Revisión: pendiente'), $this->proceso);

    expect($plan->esValido())->toBeFalse()
        ->and($plan->errores)->toBe([
            'Hoja "Matriz de Riesgos_Edición 3": no se reconoce la fecha de revisión "pendiente".',
            'Hoja "Matriz de Riesgos_Edición 3", fila 6: la periodicidad "Cada luna llena" no existe en el catálogo.',
            'Hoja "Matriz de Riesgos_Edición 3", fila 7: el factor "Desconocido" no existe en el catálogo.',
            'Hoja "Matriz de Riesgos_Edición 3", fila 8: falta la probabilidad o el impacto.',
            'Hoja "Matriz de Riesgos_Edición 3", fila 9: la probabilidad y el impacto deben ser números del 1 al 4.',
            'Hoja "Matriz de Riesgos_Edición 3", fila 10: falta la descripción del riesgo.',
            'Hoja "Matriz de Riesgos_Edición 3", fila 11: el nivel "Altísimo" no existe en el catálogo.',
        ]);
});

test('the seeded catalogue covers every periodicity found in the delivered files', function () {
    $plan = importador()->analizar(libroDeUnaHoja([LibroExcel::riesgo(['periodicidad' => 'Por cada módulo.'])]), $this->proceso);

    expect($plan->errores)->toBe([])
        ->and($plan->ediciones[0]['riesgos'][0]['datos']['periodicidad_id'])->toBe(Periodicidad::where('slug', 'por-cada-modulo')->value('id'));
});

test('a catalogue value added by the administrator is recognised without code changes', function () {
    $antes = importador()->analizar(libroDeUnaHoja([LibroExcel::riesgo(['periodicidad' => 'Semanal'])]), $this->proceso);
    Periodicidad::create(['nombre' => 'Semanal', 'slug' => 'semanal', 'frecuencia_dias' => 7]);
    $despues = importador()->analizar(libroDeUnaHoja([LibroExcel::riesgo(['periodicidad' => 'Semanal'])]), $this->proceso);

    expect($antes->esValido())->toBeFalse()
        ->and($despues->errores)->toBe([])
        ->and($despues->ediciones[0]['riesgos'][0]['datos']['periodicidad_id'])->toBe(Periodicidad::where('slug', 'semanal')->value('id'));
});

test('only a process without editions can receive an import', function () {
    Escenario::edicion($this->proceso, 'Existente', [['p' => 1, 'i' => 1]]);

    expect(importador()->analizar($this->compras, $this->proceso)->errores)
        ->toContain('El proceso "SP 4.3. Abastecimiento" ya tiene ediciones: la importación solo carga procesos sin historial.');
});

test('a file without sheets in the institutional format is rejected', function () {
    $ruta = LibroExcel::guardar(fn (Spreadsheet $l) => $l->getActiveSheet()->setCellValue('A1', 'Otra cosa'));

    expect(importador()->analizar($ruta, $this->proceso)->errores)
        ->toBe(['El archivo no contiene hojas con el formato de matriz de riesgos (RT-01-NI-ONA-156).']);
});

// --- Ejecución ------------------------------------------------------------------

test('importing creates the chained editions, the last one in force', function () {
    $ediciones = importador()->ejecutar(importador()->analizar($this->compras, $this->proceso), $this->proceso, $this->admin);

    expect($ediciones)->toHaveCount(3)
        ->and($ediciones->pluck('estado')->all())->toBe([EstadoMatriz::Archivada, EstadoMatriz::Archivada, EstadoMatriz::Vigente])
        ->and($ediciones[0]->edicion_anterior_id)->toBeNull()
        ->and($ediciones[1]->edicion_anterior_id)->toBe($ediciones[0]->id)
        ->and($ediciones[2]->edicion_anterior_id)->toBe($ediciones[1]->id)
        ->and($ediciones[2]->riesgos()->count())->toBe(3);
});

test('imported risks are linked across editions so they can be compared', function () {
    $ediciones = importador()->ejecutar(importador()->analizar($this->compras, $this->proceso), $this->proceso, $this->admin);

    $comparacion = app(ComparadorEdiciones::class)->comparar($ediciones[1], $ediciones[2]);

    expect(collect($comparacion['agregados'])->pluck('descripcion')->all())->toBe(['Riesgo nuevo en junio'])
        ->and($comparacion['cerrados'])->toHaveCount(0)
        ->and(collect($comparacion['cambios_nivel'])->pluck('descripcion')->all())->toBe(['Adjudicaciones erróneas']);
});

test('the follow up column becomes a follow up entry dated at the review date', function () {
    $ediciones = importador()->ejecutar(importador()->analizar($this->compras, $this->proceso), $this->proceso, $this->admin);

    $seguimiento = Seguimiento::sole();

    expect($seguimiento->riesgo->matriz_id)->toBe($ediciones[1]->id)
        ->and($seguimiento->user_id)->toBe($this->admin->id)
        ->and($seguimiento->fecha->toDateString())->toBe('2026-03-31')
        ->and($seguimiento->descripcion)->toBe('Importado del Excel: Verificación diaria de requisiciones.');
});

test('each imported edition is recorded in the audit log with who imported it', function () {
    $ediciones = importador()->ejecutar(importador()->analizar($this->compras, $this->proceso), $this->proceso, $this->admin);

    $registro = Auditoria::where(['evento' => 'importado', 'entidad' => 'matriz', 'entidad_id' => $ediciones[0]->id])->sole();

    expect($registro->user_id)->toBe($this->admin->id)
        ->and($registro->descripcion)->toContain('Matriz de Riesgos_Edición 3');
});

test('an invalid plan is never executed', function () {
    $plan = importador()->analizar(libroDeUnaHoja([LibroExcel::riesgo(['factor' => 'Desconocido'])]), $this->proceso);

    expect(fn () => importador()->ejecutar($plan, $this->proceso, $this->admin))->toThrow(LogicException::class);
    expect(Matriz::count())->toBe(0)->and(Riesgo::count())->toBe(0);
});
