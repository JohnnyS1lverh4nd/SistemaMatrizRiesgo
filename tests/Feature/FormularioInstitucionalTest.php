<?php

use Illuminate\Support\Facades\Schema;

/*
 * El código RT-01-NI-ONA-156 ("Edición 1") es el del formulario institucional:
 * idéntico en todas las matrices Excel. Es configuración global, no un dato
 * de cada departamento.
 */

test('institutional form code is global configuration', function () {
    expect(config('matrices.formulario.codigo'))->toBe('RT-01-NI-ONA-156')
        ->and(config('matrices.formulario.edicion'))->toBe('Edición 1');
});

test('departments no longer store their own reference code', function () {
    expect(Schema::hasColumn('departamentos', 'codigo_referencia'))->toBeFalse();
});
