<?php

use App\Enums\EstadoMatriz;
use App\Models\Auditoria;
use App\Models\Riesgo;
use App\Services\Exportacion\DatosMatriz;
use App\Services\Exportacion\ExcelMatriz;
use App\Services\Exportacion\PdfMatriz;
use App\Services\SeguimientoService;
use Database\Seeders\DatabaseSeeder;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\Support\Escenario;

/*
 * Fase 8: exportación de una edición con el formato institucional
 * RT-01-NI-ONA-156 (RF-6 y requisito de "exportación fiel").
 */

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->compras = Escenario::departamento('Compras', 'Administración y Finanzas');
    $this->admision = Escenario::departamento('Admisión');
    $proceso = Escenario::proceso($this->compras, 'SP 4.3. Abastecimiento');

    $this->matriz = Escenario::edicion($proceso, 'Abril-Junio 2026', [
        [
            'p' => 3, 'i' => 4,
            'actividad' => 'Evaluación de ofertas de los oferentes',
            'descripcion' => 'Adjudicaciones erróneas',
            'factores' => ['administrativo', 'personal'],
            'responsable' => '*Departamento de Compras. *Comité de Compras.',
            'periodicidad' => 'trimestral',
        ],
        ['p' => 2, 'i' => 2, 'descripcion' => 'Retraso en títulos', 'ajuste' => 'moderado'],
    ], fecha: '2026-06-30');

    $this->encargado = Escenario::usuario('encargado-departamento', $this->compras);
});

// --- Datos comunes (mismo formato para PDF y Excel) ---------------------------

test('export data reproduces the institutional header', function () {
    $datos = app(DatosMatriz::class)->de($this->matriz);

    expect($datos['encabezado'])->toMatchArray([
        'titulo' => 'MATRIZ DE RIESGOS',
        'codigo' => 'RT-01-NI-ONA-156',
        'edicion_formulario' => 'Edición 1',
        'direccion' => 'Administración y Finanzas',
        'proceso' => 'SP 4.3. Abastecimiento',
        'edicion' => 'Abril-Junio 2026',
        'fecha_revision' => '30 de junio de 2026',
        'estado' => 'vigente',
    ]);
});

test('export columns follow the excel groups and order', function () {
    $grupos = app(DatosMatriz::class)->de($this->matriz)['grupos'];

    expect(collect($grupos)->pluck('titulo')->all())->toBe(['Identificación de Riesgos', 'Análisis de Riesgos', 'Seguimiento y Control de Riesgos'])
        ->and(collect($grupos)->flatMap(fn ($g) => $g['columnas'])->all())->toBe([
            'Actividad', 'Riesgos', 'Fáctor', 'Causas', 'Control actual',
            'Probabilidad', 'Impacto', 'Nivel de riesgo', 'Síntoma',
            'Tratamiento', 'Responsable', 'Fecha ó Periodicidad', 'Seguimiento/ Evidencias',
        ]);
});

test('export rows carry the effective level and combined factors', function () {
    $filas = app(DatosMatriz::class)->de($this->matriz)['filas'];

    expect($filas)->toHaveCount(2)
        ->and($filas[0]['actividad'])->toBe('Evaluación de ofertas de los oferentes')
        ->and($filas[0]['factores'])->toBe('Administrativo / Personal')
        ->and($filas[0]['nivel']['nombre'])->toBe('Alto')
        ->and($filas[0]['periodicidad'])->toBe('Trimestral')
        // El nivel ajustado manualmente queda indicado.
        ->and($filas[1]['nivel']['nombre'])->toBe('Moderado')
        ->and($filas[1]['nivel_ajustado'])->toBeTrue();
});

test('follow up column lists valid entries, never voided ones', function () {
    $riesgo = Riesgo::where(['matriz_id' => $this->matriz->id, 'orden' => 1])->firstOrFail();
    $servicio = app(SeguimientoService::class);
    $servicio->registrar($riesgo, $this->encargado, ['fecha' => '2026-07-10', 'descripcion' => 'Revisión de pliegos.']);
    $anulado = $servicio->registrar($riesgo, $this->encargado, ['fecha' => '2026-07-11', 'descripcion' => 'Registro erróneo.']);
    $servicio->anular($anulado, $this->encargado, 'Duplicado');

    $seguimiento = app(DatosMatriz::class)->de($this->matriz)['filas'][0]['seguimiento'];

    expect($seguimiento)->toContain('10/07/2026: Revisión de pliegos.')
        ->and($seguimiento)->not->toContain('Registro erróneo');
});

// --- PDF ----------------------------------------------------------------------

test('pdf html contains the header, the risks and escapes user content', function () {
    $riesgo = Riesgo::where(['matriz_id' => $this->matriz->id, 'orden' => 2])->firstOrFail();
    $riesgo->forceFill(['causas' => '<script>alert(1)</script>'])->saveQuietly();

    $html = app(PdfMatriz::class)->html(app(DatosMatriz::class)->de($this->matriz));

    expect($html)->toContain('RT-01-NI-ONA-156')
        ->toContain('Administración y Finanzas')
        ->toContain('Adjudicaciones erróneas')
        ->toContain('Identificación de Riesgos')
        ->not->toContain('<script>alert(1)</script>')
        ->toContain('&lt;script&gt;');
});

test('a draft is exported with a visible draft mark', function () {
    $borrador = Escenario::edicion(Escenario::proceso($this->compras, 'Otro'), 'Borrador', [['p' => 1, 'i' => 1]], EstadoMatriz::Borrador);

    expect(app(PdfMatriz::class)->html(app(DatosMatriz::class)->de($borrador)))->toContain('BORRADOR');
});

test('the pdf endpoint returns a pdf download and records it in the audit log', function () {
    $respuesta = $this->actingAs($this->encargado)->get("/api/matrices/{$this->matriz->id}/exportar/pdf")->assertOk();

    expect($respuesta->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($respuesta->headers->get('Content-Disposition'))->toContain('attachment')
        ->and($respuesta->headers->get('Content-Disposition'))->toContain('.pdf')
        ->and(substr($respuesta->getContent(), 0, 5))->toBe('%PDF-');

    expect(Auditoria::where(['evento' => 'exportado', 'entidad' => 'matriz', 'entidad_id' => $this->matriz->id])->value('descripcion'))
        ->toBe('PDF');
});

// --- Excel --------------------------------------------------------------------

/** Genera el .xlsx y lo vuelve a abrir, como lo haría Excel. */
function hojaExportada(array $datos): Worksheet
{
    $ruta = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($ruta, app(ExcelMatriz::class)->generar($datos));

    try {
        return IOFactory::createReader('Xlsx')->load($ruta)->getActiveSheet();
    } finally {
        unlink($ruta);
    }
}

test('excel reproduces the institutional header and column groups', function () {
    $hoja = hojaExportada(app(DatosMatriz::class)->de($this->matriz));

    expect($hoja->getCell('A1')->getValue())->toBe('MATRIZ DE RIESGOS')
        ->and($hoja->getCell('J1')->getValue())->toBe('RT-01-NI-ONA-156 Edición 1')
        ->and($hoja->getCell('A2')->getValue())->toBe('DIRECCIÓN: Administración y Finanzas')
        ->and($hoja->getCell('E2')->getValue())->toBe('PROCESO: SP 4.3. Abastecimiento')
        ->and($hoja->getCell('J2')->getValue())->toBe("FECHA DE REVISIÓN: 30 de junio de 2026\nEDICIÓN: Abril-Junio 2026")
        ->and($hoja->getCell('A4')->getValue())->toBe('Identificación de Riesgos')
        ->and($hoja->getCell('F4')->getValue())->toBe('Análisis de Riesgos')
        ->and($hoja->getCell('J4')->getValue())->toBe('Seguimiento y Control de Riesgos')
        ->and($hoja->getCell('A5')->getValue())->toBe('Actividad')
        ->and($hoja->getCell('M5')->getValue())->toBe('Seguimiento/ Evidencias')
        ->and($hoja->getMergeCells())->toHaveKeys(['A1:I1', 'J1:M1', 'A4:E4', 'F4:I4', 'J4:M4']);
});

test('excel has one row per risk with the effective level and its colour', function () {
    $hoja = hojaExportada(app(DatosMatriz::class)->de($this->matriz));

    expect($hoja->getCell('A6')->getValue())->toBe('Evaluación de ofertas de los oferentes')
        ->and($hoja->getCell('B6')->getValue())->toBe('Adjudicaciones erróneas')
        ->and($hoja->getCell('C6')->getValue())->toBe('Administrativo / Personal')
        ->and($hoja->getCell('F6')->getValue())->toBe(3)
        ->and($hoja->getCell('G6')->getValue())->toBe(4)
        ->and($hoja->getCell('H6')->getValue())->toBe('Alto')
        ->and($hoja->getStyle('H6')->getFill()->getStartColor()->getRGB())->toBe('F97316')
        ->and($hoja->getCell('L6')->getValue())->toBe('Trimestral')
        // El ajuste manual queda indicado junto al nivel.
        ->and($hoja->getCell('H7')->getValue())->toStartWith('Moderado')
        ->and($hoja->getCell('H7')->getValue())->toContain('Ajustado')
        ->and($hoja->getHighestRow())->toBe(7);
});

test('excel stores user text as plain strings, never as formulas', function () {
    $riesgo = Riesgo::where(['matriz_id' => $this->matriz->id, 'orden' => 1])->firstOrFail();
    $riesgo->forceFill(['causas' => '=HYPERLINK("http://ejemplo.test","clic")'])->saveQuietly();

    $celda = hojaExportada(app(DatosMatriz::class)->de($this->matriz))->getCell('D6');

    expect($celda->getDataType())->toBe(DataType::TYPE_STRING)
        ->and($celda->getValue())->toBe('=HYPERLINK("http://ejemplo.test","clic")');
});

test('a draft is exported to excel with a visible draft mark', function () {
    $borrador = Escenario::edicion(Escenario::proceso($this->compras, 'Otro'), 'Borrador', [['p' => 1, 'i' => 1]], EstadoMatriz::Borrador);

    expect(hojaExportada(app(DatosMatriz::class)->de($borrador))->getCell('A3')->getValue())->toContain('BORRADOR')
        ->and(hojaExportada(app(DatosMatriz::class)->de($this->matriz))->getCell('A3')->getValue())->toBeNull();
});

test('the excel endpoint returns an xlsx download and records it in the audit log', function () {
    $respuesta = $this->actingAs($this->encargado)->get("/api/matrices/{$this->matriz->id}/exportar/excel")->assertOk();

    expect($respuesta->headers->get('Content-Type'))->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->and($respuesta->headers->get('Content-Disposition'))->toContain('.xlsx')
        // Un .xlsx es un ZIP: empieza por "PK".
        ->and(substr($respuesta->getContent(), 0, 2))->toBe('PK');

    expect(Auditoria::where(['evento' => 'exportado', 'entidad' => 'matriz', 'entidad_id' => $this->matriz->id])->value('descripcion'))
        ->toBe('Excel');
});

test('exports respect the departamento scope', function () {
    $ajeno = Escenario::usuario('encargado-departamento', $this->admision);

    $this->actingAs($ajeno)->getJson("/api/matrices/{$this->matriz->id}/exportar/pdf")->assertForbidden();
    $this->actingAs($ajeno)->getJson("/api/matrices/{$this->matriz->id}/exportar/excel")->assertForbidden();
    $this->actingAs(Escenario::usuario('auditor'))->get("/api/matrices/{$this->matriz->id}/exportar/pdf")->assertOk();
    $this->actingAs(Escenario::usuario('auditor'))->get("/api/matrices/{$this->matriz->id}/exportar/excel")->assertOk();
});
