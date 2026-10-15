<?php

use App\Enums\EstadoMatriz;
use App\Models\Matriz;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\Support\Escenario;
use Tests\Support\LibroExcel;

/*
 * Fase 9: importación desde la pantalla web. El archivo se sube en la vista
 * previa y otra vez al confirmar (sin estado en el servidor).
 */

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->departamento = Escenario::departamento('Compras', 'Administración y Finanzas');
    $this->proceso = Escenario::proceso($this->departamento, 'SP 4.3. Abastecimiento');
    $this->admin = Escenario::usuario('administrador');
});

afterEach(fn () => LibroExcel::limpiar());

/** @param  array<int, array<int, mixed>>|null  $filas */
function excelSubido(?array $filas = null, string $nombre = 'Matriz Compras.xlsx'): UploadedFile
{
    $ruta = LibroExcel::guardar(fn (Spreadsheet $l) => LibroExcel::hoja(
        $l->getActiveSheet()->setTitle('ABRIL-JUNIO 2026'),
        'FECHA DE REVISIÓN: JUNIO 2026',
        $filas ?? [LibroExcel::riesgo(), LibroExcel::riesgo(['descripcion' => 'Otro', 'nivel' => 'Moderado', 'seguimiento' => 'Hecho.'])],
    ));

    return new UploadedFile($ruta, $nombre, null, null, true);
}

test('the preview returns the plan and writes nothing', function () {
    $this->actingAs($this->admin)
        ->post("/api/procesos/{$this->proceso->id}/importacion/vista-previa", ['archivo' => excelSubido()], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.archivo', 'Matriz Compras.xlsx')
        ->assertJsonPath('data.valido', true)
        ->assertJsonPath('data.ediciones.0.nombre', 'Abril-Junio 2026')
        ->assertJsonPath('data.ediciones.0.riesgos', 2)
        ->assertJsonPath('data.ediciones.0.seguimientos', 1)
        ->assertJsonPath('data.ediciones.0.ajustes', 1)
        ->assertJsonCount(0, 'data.errores');

    expect(Matriz::count())->toBe(0);
});

test('the preview also reports errors with sheet and row', function () {
    $this->actingAs($this->admin)
        ->post("/api/procesos/{$this->proceso->id}/importacion/vista-previa", ['archivo' => excelSubido([LibroExcel::riesgo(['factor' => 'Desconocido'])])], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.valido', false)
        ->assertJsonPath('data.errores.0', 'Hoja "ABRIL-JUNIO 2026", fila 6: el factor "Desconocido" no existe en el catálogo.');
});

test('confirming imports the editions', function () {
    $this->actingAs($this->admin)
        ->post("/api/procesos/{$this->proceso->id}/importacion", ['archivo' => excelSubido()], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.0.nombre', 'Abril-Junio 2026')
        ->assertJsonPath('data.0.estado', 'vigente');

    expect(Matriz::sole()->estado)->toBe(EstadoMatriz::Vigente);
});

test('confirming an invalid file imports nothing and returns the errors', function () {
    $this->actingAs($this->admin)
        ->post("/api/procesos/{$this->proceso->id}/importacion", ['archivo' => excelSubido([LibroExcel::riesgo(['factor' => 'Desconocido'])])], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.archivo.0', 'Hoja "ABRIL-JUNIO 2026", fila 6: el factor "Desconocido" no existe en el catálogo.');

    expect(Matriz::count())->toBe(0);
});

test('only spreadsheets are accepted', function (UploadedFile $archivo) {
    $this->actingAs($this->admin)
        ->post("/api/procesos/{$this->proceso->id}/importacion/vista-previa", ['archivo' => $archivo], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('archivo');
})->with([
    'pdf' => fn () => UploadedFile::fake()->create('matriz.pdf', 10, 'application/pdf'),
    // Archivo real (no fake): el tipo se detecta por el contenido, como en una subida de verdad.
    'text renamed to xlsx' => function () {
        $ruta = tempnam(sys_get_temp_dir(), 'imp');
        file_put_contents($ruta, 'no es un excel');

        return new UploadedFile($ruta, 'matriz.xlsx', null, null, true);
    },
    'too large' => fn () => UploadedFile::fake()->create('matriz.xlsx', 6 * 1024, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
]);

test('only the administrator can import', function (string $rol) {
    $usuario = Escenario::usuario($rol, $this->departamento);

    $this->actingAs($usuario)
        ->post("/api/procesos/{$this->proceso->id}/importacion/vista-previa", ['archivo' => excelSubido()], ['Accept' => 'application/json'])
        ->assertForbidden();
    $this->actingAs($usuario)
        ->post("/api/procesos/{$this->proceso->id}/importacion", ['archivo' => excelSubido()], ['Accept' => 'application/json'])
        ->assertForbidden();

    expect(Matriz::count())->toBe(0);
})->with(['encargado-departamento', 'encargado-seguimiento', 'auditor', 'direccion']);

test('guests cannot import', function () {
    $this->post("/api/procesos/{$this->proceso->id}/importacion", ['archivo' => excelSubido()], ['Accept' => 'application/json'])
        ->assertUnauthorized();
});

test('the process exposes whether the user may import, for the frontend', function () {
    $this->actingAs($this->admin)->getJson("/api/procesos/{$this->proceso->id}")
        ->assertJsonPath('data.permisos.importar', true);

    $this->actingAs(Escenario::usuario('encargado-departamento', $this->departamento))->getJson("/api/procesos/{$this->proceso->id}")
        ->assertJsonPath('data.permisos.importar', false);
});
