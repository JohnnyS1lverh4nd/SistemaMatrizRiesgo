<?php

use App\Models\MatrizCalorCelda;
use App\Models\NivelRiesgo;
use Database\Seeders\MatrizCalorCeldaSeeder;
use Database\Seeders\NivelRiesgoSeeder;

/*
 * Regla inicial de la matriz de calor: suma Probabilidad + Impacto.
 * No hay tabla oficial disponible; se eligió la regla que mejor reproduce
 * las matrices Excel de INFOTEP (13 de 15 filas evaluadas, oct. 2026).
 * El Administrador puede ajustar cualquier celda después.
 */

beforeEach(function () {
    $this->seed([NivelRiesgoSeeder::class, MatrizCalorCeldaSeeder::class]);
});

function nivelDeCelda(int $probabilidad, int $impacto): string
{
    return MatrizCalorCelda::with('nivelRiesgo')
        ->where(['probabilidad' => $probabilidad, 'impacto' => $impacto])
        ->firstOrFail()
        ->nivelRiesgo
        ->slug;
}

test('heat map follows the sum rule', function (int $probabilidad, int $impacto, string $esperado) {
    expect(nivelDeCelda($probabilidad, $impacto))->toBe($esperado);
})->with(function () {
    foreach (range(1, 4) as $p) {
        foreach (range(1, 4) as $i) {
            $nivel = match (true) {
                $p + $i <= 4 => 'bajo',
                $p + $i <= 6 => 'moderado',
                $p + $i === 7 => 'alto',
                default => 'critico',
            };
            yield "P{$p} x I{$i}" => [$p, $i, $nivel];
        }
    }
});

test('heat map reproduces the levels used in the institutional excel files', function (int $probabilidad, int $impacto, string $esperado) {
    expect(nivelDeCelda($probabilidad, $impacto))->toBe($esperado);
})->with([
    'Admisión: recepción de solicitudes' => [3, 3, 'moderado'],
    'Compras: materiales no disponibles' => [4, 3, 'alto'],
    'Compras: adjudicaciones erróneas' => [3, 4, 'alto'],
    'CTIVPR/SFP: egresados sin competencias' => [2, 3, 'moderado'],
    'CTIVPR/SFP: acciones formativas' => [1, 4, 'moderado'],
    'SFP: retraso en títulos' => [2, 2, 'bajo'],
    // SFP clasifica dos riesgos 3x4 como Moderado (frente a Alto en Compras):
    // se tratarán como ajustes manuales justificados (RF-3).
]);

test('seeder is idempotent and keeps sixteen cells', function () {
    $this->seed(MatrizCalorCeldaSeeder::class);

    expect(MatrizCalorCelda::count())->toBe(16);
});

test('running the seeder again keeps the administrator adjustments', function () {
    $celda = MatrizCalorCelda::where(['probabilidad' => 3, 'impacto' => 4])->firstOrFail();
    $moderado = NivelRiesgo::where('slug', 'moderado')->value('id');
    $celda->update(['nivel_riesgo_id' => $moderado]);

    $this->seed(MatrizCalorCeldaSeeder::class);

    expect(nivelDeCelda(3, 4))->toBe('moderado');
});
