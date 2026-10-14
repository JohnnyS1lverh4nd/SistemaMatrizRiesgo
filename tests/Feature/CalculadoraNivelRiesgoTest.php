<?php

use App\Models\MatrizCalorCelda;
use App\Models\NivelRiesgo;
use App\Services\CalculadoraNivelRiesgo;
use Database\Seeders\MatrizCalorCeldaSeeder;
use Database\Seeders\NivelRiesgoSeeder;

/*
 * Única fuente de verdad del nivel de riesgo: la matriz de calor configurada.
 */

beforeEach(function () {
    $this->seed([NivelRiesgoSeeder::class, MatrizCalorCeldaSeeder::class]);
    $this->calculadora = app(CalculadoraNivelRiesgo::class);
});

test('level comes from the heat map cell', function (int $p, int $i, string $esperado) {
    expect($this->calculadora->calcular($p, $i)?->slug)->toBe($esperado);
})->with([
    [1, 1, 'bajo'],
    [2, 2, 'bajo'],
    [3, 3, 'moderado'],
    [4, 3, 'alto'],
    [3, 4, 'alto'],
    [4, 4, 'critico'],
]);

test('level follows the administrator adjustments of the heat map', function () {
    MatrizCalorCelda::where(['probabilidad' => 3, 'impacto' => 4])
        ->update(['nivel_riesgo_id' => NivelRiesgo::where('slug', 'moderado')->value('id')]);

    expect($this->calculadora->calcular(3, 4)?->slug)->toBe('moderado');
});

test('an unevaluated risk has no level', function () {
    expect($this->calculadora->calcular(null, null))->toBeNull();
});

test('values outside the 1 to 4 scale are rejected', function (int $p, int $i) {
    expect(fn () => $this->calculadora->calcular($p, $i))->toThrow(InvalidArgumentException::class);
})->with([[0, 1], [5, 1], [1, 0], [1, 5]]);

test('probability and impact must be given together', function () {
    expect(fn () => $this->calculadora->calcular(3, null))->toThrow(InvalidArgumentException::class);
});
