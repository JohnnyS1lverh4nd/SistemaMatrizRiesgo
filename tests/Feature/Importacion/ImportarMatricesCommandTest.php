<?php

use App\Models\Matriz;
use Database\Seeders\DatabaseSeeder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\Support\Escenario;
use Tests\Support\LibroExcel;

/*
 * Fase 9: comando matrices:importar. Sin --confirmar solo muestra el plan.
 */

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->departamento = Escenario::departamento('Compras', 'Administración y Finanzas');
    $this->proceso = Escenario::proceso($this->departamento, 'SP 4.3. Abastecimiento');
    $this->admin = Escenario::usuario('administrador');
    $this->archivo = LibroExcel::guardar(fn (Spreadsheet $l) => LibroExcel::hoja(
        $l->getActiveSheet()->setTitle('ABRIL-JUNIO 2026'),
        'FECHA DE REVISIÓN: JUNIO 2026',
        [LibroExcel::riesgo(), LibroExcel::riesgo(['descripcion' => 'Otro', 'nivel' => 'Moderado'])],
    ));
});

afterEach(fn () => LibroExcel::limpiar());

test('without --confirmar it only shows the plan', function () {
    $this->artisan('matrices:importar', ['archivo' => $this->archivo, '--proceso' => $this->proceso->id, '--usuario' => $this->admin->email])
        ->expectsOutputToContain('Abril-Junio 2026')
        ->expectsOutputToContain('ajuste manual')
        ->expectsOutputToContain('Vista previa: no se ha importado nada')
        ->assertSuccessful();

    expect(Matriz::count())->toBe(0);
});

test('with --confirmar it imports and reports what was created', function () {
    $this->artisan('matrices:importar', ['archivo' => $this->archivo, '--proceso' => $this->proceso->id, '--usuario' => $this->admin->email, '--confirmar' => true])
        ->expectsOutputToContain('Importadas 1 edición(es)')
        ->assertSuccessful();

    expect(Matriz::sole()->riesgos()->count())->toBe(2);
});

test('a plan with errors fails and imports nothing', function () {
    $archivo = LibroExcel::guardar(fn (Spreadsheet $l) => LibroExcel::hoja($l->getActiveSheet(), 'FECHA DE REVISIÓN: JUNIO 2026', [LibroExcel::riesgo(['factor' => 'Desconocido'])]));

    $this->artisan('matrices:importar', ['archivo' => $archivo, '--proceso' => $this->proceso->id, '--usuario' => $this->admin->email, '--confirmar' => true])
        ->expectsOutputToContain('el factor "Desconocido" no existe en el catálogo')
        ->assertFailed();

    expect(Matriz::count())->toBe(0);
});

test('only an active administrator can import', function () {
    $encargado = Escenario::usuario('encargado-departamento', $this->departamento);

    $this->artisan('matrices:importar', ['archivo' => $this->archivo, '--proceso' => $this->proceso->id, '--usuario' => $encargado->email, '--confirmar' => true])
        ->expectsOutputToContain('Solo un Administrador activo puede importar matrices.')
        ->assertFailed();

    $this->admin->forceFill(['activo' => false])->save();
    $this->artisan('matrices:importar', ['archivo' => $this->archivo, '--proceso' => $this->proceso->id, '--usuario' => $this->admin->email, '--confirmar' => true])
        ->assertFailed();

    expect(Matriz::count())->toBe(0);
});

test('missing or unknown options are reported', function () {
    $this->artisan('matrices:importar', ['archivo' => $this->archivo, '--usuario' => $this->admin->email])
        ->expectsOutputToContain('Indique el proceso')
        ->assertFailed();

    $this->artisan('matrices:importar', ['archivo' => $this->archivo, '--proceso' => 999, '--usuario' => $this->admin->email])
        ->expectsOutputToContain('No existe el proceso 999')
        ->assertFailed();

    $this->artisan('matrices:importar', ['archivo' => $this->archivo, '--proceso' => $this->proceso->id, '--usuario' => 'nadie@example.com'])
        ->expectsOutputToContain('No existe el usuario')
        ->assertFailed();
});
