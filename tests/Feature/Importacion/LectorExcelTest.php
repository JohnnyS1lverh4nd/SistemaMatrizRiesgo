<?php

use App\Services\Exportacion\DatosMatriz;
use App\Services\Exportacion\ExcelMatriz;
use App\Services\Importacion\LectorExcel;
use Database\Seeders\DatabaseSeeder;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\Support\Escenario;
use Tests\Support\LibroExcel;

/*
 * Fase 9: lectura de los Excel institucionales (formato RT-01-NI-ONA-156).
 * Los libros se construyen aquí con las mismas particularidades que los
 * archivos reales: celdas combinadas, texto enriquecido, hojas ocultas.
 */

afterEach(fn () => LibroExcel::limpiar());

test('reads the header and one row per risk with the 13 columns', function () {
    $ruta = LibroExcel::guardar(fn (Spreadsheet $l) => LibroExcel::hoja($l->getActiveSheet()->setTitle('ABRIL-JUNIO 2026'), 'FECHA DE REVISIÓN: JUNIO 2026', [
        ['Evaluación de ofertas', 'Adjudicaciones erróneas', 'Administrativo', 'Criterios ambiguos', 'Pautas a peritos', 3, 4, 'Alto', 'Demandas', 'Revisión de pliegos', 'Comité de Compras', 'Revisión cada tres meses', 'Pliegos entregados'],
    ]));

    $hojas = app(LectorExcel::class)->leer($ruta);

    expect($hojas)->toHaveCount(1)
        ->and($hojas[0]['nombre'])->toBe('ABRIL-JUNIO 2026')
        ->and($hojas[0]['encabezado'])->toBe([
            'direccion' => 'Administración y Finanzas',
            'proceso' => 'SP 4.3. Abastecimiento',
            'fecha_revision' => 'JUNIO 2026',
        ])
        ->and($hojas[0]['filas'])->toHaveCount(1)
        ->and($hojas[0]['filas'][0])->toBe([
            'fila' => 6,
            'actividad' => 'Evaluación de ofertas',
            'descripcion' => 'Adjudicaciones erróneas',
            'factor' => 'Administrativo',
            'causas' => 'Criterios ambiguos',
            'control_actual' => 'Pautas a peritos',
            'probabilidad' => '3',
            'impacto' => '4',
            'nivel' => 'Alto',
            'sintoma' => 'Demandas',
            'tratamiento' => 'Revisión de pliegos',
            'responsable' => 'Comité de Compras',
            'periodicidad' => 'Revisión cada tres meses',
            'seguimiento' => 'Pliegos entregados',
        ]);
});

test('merged cells are copied to every sub-row they span', function () {
    // Como SFP: un riesgo (B, F–H combinados) con tres causas, y la
    // actividad (A) compartida con el riesgo siguiente.
    $ruta = LibroExcel::guardar(function (Spreadsheet $l) {
        $hoja = $l->getActiveSheet();
        LibroExcel::hoja($hoja, 'Fecha de Revisión: Junio 2026', [
            LibroExcel::fila([0 => 'Acciones formativas', 1 => 'Egresados sin competencias', 2 => 'Personal', 3 => 'Facilitador', 5 => 2, 6 => 3, 7 => 'Moderado']),
            LibroExcel::fila([2 => 'Administrativo', 3 => 'Retraso de materiales']),
            LibroExcel::fila([2 => 'Personal', 3 => 'Calificación sin requisitos']),
            LibroExcel::fila([1 => 'Suspensión de acciones', 2 => 'Político-social-ambiental', 5 => 1, 6 => 4, 7 => 'Moderado']),
        ]);
        foreach (['A6:A9', 'B6:B8', 'F6:F8', 'G6:G8', 'H6:H8'] as $rango) {
            $hoja->mergeCells($rango);
        }
    });

    $filas = app(LectorExcel::class)->leer($ruta)[0]['filas'];

    expect($filas)->toHaveCount(4)
        ->and(array_column($filas, 'actividad'))->toBe(array_fill(0, 4, 'Acciones formativas'))
        ->and(array_column($filas, 'descripcion'))->toBe(['Egresados sin competencias', 'Egresados sin competencias', 'Egresados sin competencias', 'Suspensión de acciones'])
        ->and(array_column($filas, 'causas'))->toBe(['Facilitador', 'Retraso de materiales', 'Calificación sin requisitos', null])
        ->and(array_column($filas, 'probabilidad'))->toBe(['2', '2', '2', '1'])
        ->and(array_column($filas, 'nivel'))->toBe(['Moderado', 'Moderado', 'Moderado', 'Moderado'])
        ->and(array_column($filas, 'fila'))->toBe([6, 7, 8, 9]);
});

test('rich text becomes plain text and whitespace is normalised', function () {
    $ruta = LibroExcel::guardar(function (Spreadsheet $l) {
        $hoja = $l->getActiveSheet();
        LibroExcel::hoja($hoja, 'x', [LibroExcel::fila([0 => 'A', 1 => "\nRecepción de solicitudes   ", 5 => 3, 6 => 3])]);
        $enriquecido = new RichText;
        $enriquecido->createText('Fecha de Revisión:  ');
        $enriquecido->createTextRun('Junio 2026')->getFont()?->setBold(true);
        $hoja->getCell('J2')->setValue($enriquecido);
        // Viñetas separadas con espacios en lugar de saltos de línea, como en Compras.
        $hoja->setCellValue('D6', '* Requerimientos tardíos.        * No planificar las necesidades.');
    });

    $hoja = app(LectorExcel::class)->leer($ruta)[0];

    expect($hoja['encabezado']['fecha_revision'])->toBe('Junio 2026')
        ->and($hoja['filas'][0]['descripcion'])->toBe('Recepción de solicitudes')
        ->and($hoja['filas'][0]['causas'])->toBe("* Requerimientos tardíos.\n* No planificar las necesidades.");
});

test('empty rows, hidden sheets and sheets without the institutional layout are skipped', function () {
    $ruta = LibroExcel::guardar(function (Spreadsheet $l) {
        LibroExcel::hoja($l->getActiveSheet(), 'x', [
            LibroExcel::fila([0 => 'A', 1 => 'Riesgo 1', 5 => 1, 6 => 1]),
            LibroExcel::fila([]),
            LibroExcel::fila([0 => 'B', 1 => 'Riesgo 2', 5 => 2, 6 => 2]),
        ]);
        // Celdas con formato pero vacías hasta muy abajo (como Admisión, A1:AA993).
        $l->getActiveSheet()->getStyle('A900:M993')->getFont()->setBold(true);

        $oculta = $l->createSheet()->setTitle('Hoja1');
        $oculta->fromArray([['Bajo'], ['Moderado'], ['Alto'], ['Crítico']]);
        $oculta->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        $l->createSheet()->setTitle('Notas')->setCellValue('A1', 'Texto libre');
    });

    $hojas = app(LectorExcel::class)->leer($ruta);

    expect($hojas)->toHaveCount(1)
        ->and(array_column($hojas[0]['filas'], 'descripcion'))->toBe(['Riesgo 1', 'Riesgo 2']);
});

test('a file that is not a readable spreadsheet is rejected with a clear message', function () {
    $ruta = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
    file_put_contents($ruta, 'no es un excel');

    expect(fn () => app(LectorExcel::class)->leer($ruta))
        ->toThrow(InvalidArgumentException::class, 'no es un archivo Excel válido');
});

test('a file exported by the system can be read back without losing rows', function () {
    // El Excel exportado tiene la cabecera de columnas en una sola fila (no combinada).
    $this->seed(DatabaseSeeder::class);
    $proceso = Escenario::proceso(Escenario::departamento('Compras'), 'SP 4.3. Abastecimiento');
    $matriz = Escenario::edicion($proceso, 'Abril-Junio 2026', [['p' => 3, 'i' => 4, 'descripcion' => 'Primero'], ['p' => 1, 'i' => 1, 'descripcion' => 'Segundo']]);

    $ruta = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
    file_put_contents($ruta, app(ExcelMatriz::class)->generar(app(DatosMatriz::class)->de($matriz)));

    expect(array_column(app(LectorExcel::class)->leer($ruta)[0]['filas'], 'descripcion'))->toBe(['Primero', 'Segundo']);
});
