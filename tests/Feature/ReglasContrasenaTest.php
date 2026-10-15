<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/*
 * Reglas de contraseña en producción (decisión oct. 2026): mínimo 8
 * caracteres, mayúsculas y minúsculas, números y símbolos, y que no figure
 * en filtraciones conocidas. En desarrollo y pruebas no hay mínimo extra.
 */

function contrasenaValidaEnProduccion(string $contrasena): bool
{
    app()->detectEnvironment(fn () => 'production');
    // uncompromised() consulta haveibeenpwned: sin red en las pruebas.
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);

    return Validator::make(['password' => $contrasena], ['password' => Password::default()])->passes();
}

afterEach(fn () => app()->detectEnvironment(fn () => 'testing'));

test('in production a password needs at least 8 characters', function () {
    expect(contrasenaValidaEnProduccion('Ab1!efgh'))->toBeTrue()
        ->and(contrasenaValidaEnProduccion('Ab1!efg'))->toBeFalse();
});

test('in production a password still needs mixed case, numbers and symbols', function (string $contrasena) {
    expect(contrasenaValidaEnProduccion($contrasena))->toBeFalse();
})->with([
    'sin mayúsculas' => 'ab1!efgh',
    'sin números' => 'Abc!efgh',
    'sin símbolos' => 'Ab12efgh',
]);
