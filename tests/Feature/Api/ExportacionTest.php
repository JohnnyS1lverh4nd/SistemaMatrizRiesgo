<?php

use App\Enums\EstadoMatriz;
use App\Models\Auditoria;
use App\Models\Riesgo;
use App\Services\Exportacion\DatosMatriz;
use App\Services\Exportacion\ExcelMatriz;
use App\Services\Exportacion\PdfMatriz;
use App\Services\Importacion\ImportadorMatrices;
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

test('pdf follows the institutional template and carries the logo', function () {
    $html = app(PdfMatriz::class)->html(app(DatosMatriz::class)->de($this->matriz));

    expect($html)->toContain('data:image/png;base64,')
        ->toContain('MATRIZ DE RIESGOS')
        ->toContain('FECHA DE REVISIÓN:')
        ->toContain('Abril-Junio 2026')
        // Colores de la plantilla: grupos y nivel Alto.
        ->toContain('#F4B184')
        ->toContain('#FFD966')
        ->toContain('#C5E0B4');
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

// --- Excel (misma hoja que las plantillas institucionales) -------------------

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

function relleno(Worksheet $hoja, string $celda): string
{
    return $hoja->getStyle($celda)->getFill()->getStartColor()->getRGB();
}

test('excel reproduces the institutional template header', function () {
    $hoja = hojaExportada(app(DatosMatriz::class)->de($this->matriz));

    expect($hoja->getTitle())->toBe('Abril-Junio 2026')
        ->and($hoja->getCell('A1')->getValue())->toBe('MATRIZ DE RIESGOS')
        ->and($hoja->getStyle('A1')->getFont()->getSize())->toEqual(36)
        ->and($hoja->getCell('K1')->getValue())->toBe("RT-01-NI-ONA-156\nEdición 1")
        ->and($hoja->getCell('A2')->getValue())->toBe('DIRECCIÓN: Administración y Finanzas')
        ->and($hoja->getCell('C2')->getValue())->toBe('PROCESO: SP 4.3. Abastecimiento')
        ->and($hoja->getCell('J2')->getValue())->toBe('FECHA DE REVISIÓN: 30 de junio de 2026')
        ->and([relleno($hoja, 'A1'), relleno($hoja, 'A2')])->toBe(['F2F2F2', 'E7E6E6'])
        ->and([$hoja->getCell('A3')->getValue(), $hoja->getCell('F3')->getValue(), $hoja->getCell('J3')->getValue()])
        ->toBe(['Identificación de Riesgos', 'Análisis de Riesgos', 'Seguimiento y Control de Riesgos'])
        ->and([relleno($hoja, 'A3'), relleno($hoja, 'F3'), relleno($hoja, 'J3')])->toBe(['F4B184', 'FFD966', 'C5E0B4'])
        ->and($hoja->getCell('A4')->getValue())->toBe('Actividad')
        ->and($hoja->getCell('M4')->getValue())->toBe('Seguimiento/ Evidencias')
        ->and(array_keys($hoja->getMergeCells()))->toEqualCanonicalizing([
            'A1:I1', 'K1:M1', 'A2:B2', 'C2:I2', 'J2:M2', 'A3:E3', 'F3:I3', 'J3:M3',
            'A4:A5', 'B4:B5', 'C4:C5', 'D4:D5', 'E4:E5', 'F4:F5', 'G4:G5', 'H4:H5', 'I4:I5', 'J4:J5', 'K4:K5', 'L4:L5', 'M4:M5',
        ])
        ->and(round($hoja->getColumnDimension('M')->getWidth(), 1))->toBe(70.6);
});

test('excel carries the institution logo at the top left', function () {
    $dibujos = hojaExportada(app(DatosMatriz::class)->de($this->matriz))->getDrawingCollection();

    expect($dibujos)->toHaveCount(1)
        ->and($dibujos[0]->getCoordinates())->toBe('A1')
        ->and($dibujos[0]->getName())->toBe('Logo INFOTEP');
});

test('excel has one row per risk from row 6 with the template level colours', function () {
    $hoja = hojaExportada(app(DatosMatriz::class)->de($this->matriz));

    expect($hoja->getCell('A6')->getValue())->toBe('Evaluación de ofertas de los oferentes')
        ->and($hoja->getCell('C6')->getValue())->toBe('Administrativo / Personal')
        ->and($hoja->getCell('F6')->getValue())->toBe(3)
        ->and($hoja->getCell('G6')->getValue())->toBe(4)
        ->and($hoja->getCell('H6')->getValue())->toBe('Alto')
        ->and(relleno($hoja, 'H6'))->toBe('F4B184')
        ->and($hoja->getCell('L6')->getValue())->toBe('Trimestral')
        // Seguimiento como en la plantilla: azul y en negrita.
        ->and($hoja->getStyle('M6')->getFont()->getColor()->getRGB())->toBe('2E75B5')
        // El ajuste manual queda indicado junto al nivel.
        ->and($hoja->getCell('H7')->getValue())->toStartWith('Moderado')
        ->and($hoja->getCell('H7')->getValue())->toContain('Ajustado')
        ->and(relleno($hoja, 'H7'))->toBe('FFD966')
        ->and($hoja->getHighestRow())->toBe(7);
});

test('low and critical levels use the template colours', function () {
    $proceso = Escenario::proceso($this->compras, 'Otro');
    $matriz = Escenario::edicion($proceso, 'Extremos', [['p' => 1, 'i' => 1], ['p' => 4, 'i' => 4]]);

    $hoja = hojaExportada(app(DatosMatriz::class)->de($matriz));

    expect([$hoja->getCell('H6')->getValue(), relleno($hoja, 'H6')])->toBe(['Bajo', 'A9D18E'])
        ->and([$hoja->getCell('H7')->getValue(), relleno($hoja, 'H7')])->toBe(['Crítico', 'C00000'])
        ->and($hoja->getStyle('H7')->getFont()->getColor()->getRGB())->toBe('FFFFFF');
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

    expect(hojaExportada(app(DatosMatriz::class)->de($borrador))->getCell('J1')->getValue())->toBe('BORRADOR')
        ->and(hojaExportada(app(DatosMatriz::class)->de($this->matriz))->getCell('J1')->getValue())->toBeNull();
});

test('the sheet name is the edition, made valid for excel', function () {
    $matriz = Escenario::edicion(Escenario::proceso($this->compras, 'Otro'), 'Ene/Mar 2026: revisión [final] del proceso de compras', [['p' => 1, 'i' => 1]]);

    $titulo = hojaExportada(app(DatosMatriz::class)->de($matriz))->getTitle();

    expect(strpbrk($titulo, '\\/?*[]:'))->toBeFalse()
        ->and(mb_strlen($titulo))->toBeLessThanOrEqual(31)
        ->and($titulo)->toStartWith('Ene-Mar 2026');
});

test('an exported excel can be imported back into a new process', function () {
    $ruta = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
    file_put_contents($ruta, app(ExcelMatriz::class)->generar(app(DatosMatriz::class)->de($this->matriz)));
    $destino = Escenario::proceso($this->admision, 'Copia de Compras');

    $plan = app(ImportadorMatrices::class)->analizar($ruta, $destino);
    unlink($ruta);

    expect($plan->errores)->toBe([])
        ->and($plan->ediciones[0]['nombre'])->toBe('Abril-Junio 2026')
        ->and($plan->ediciones[0]['fecha_revision'])->toBe('2026-06-30')
        ->and($plan->ediciones[0]['riesgos'])->toHaveCount(2)
        // El ajuste manual vuelve con su justificación original, no con la genérica.
        ->and($plan->ediciones[0]['riesgos'][1]['datos']['justificacion_ajuste'])->toBe('Ajuste de prueba');
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
