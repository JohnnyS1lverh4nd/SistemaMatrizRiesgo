<?php

use Illuminate\Testing\TestResponse;

/*
 * La SPA (otro origen) llama a la API con cookies: cada ruta que use el
 * frontend debe responder al preflight CORS. Sin esto el navegador bloquea
 * la petición aunque Laravel la acepte (las demás pruebas no lo detectan).
 */

function preflight(string $uri, string $metodo, string $origen): TestResponse
{
    return test()->call('OPTIONS', $uri, server: [
        'HTTP_ORIGIN' => $origen,
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => $metodo,
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type,x-xsrf-token',
    ]);
}

test('frontend routes answer the cors preflight with credentials', function (string $uri, string $metodo) {
    $frontend = config('app.frontend_url');

    preflight($uri, $metodo, $frontend)
        ->assertHeader('Access-Control-Allow-Origin', $frontend)
        ->assertHeader('Access-Control-Allow-Credentials', 'true');
})->with([
    'csrf cookie' => ['/sanctum/csrf-cookie', 'GET'],
    'login' => ['/login', 'POST'],
    'logout' => ['/logout', 'POST'],
    'olvidé contraseña' => ['/forgot-password', 'POST'],
    'restablecer contraseña' => ['/reset-password', 'POST'],
    'cambiar contraseña' => ['/user/password', 'PUT'],
    'api' => ['/api/user', 'GET'],
]);

test('other origins are not allowed', function () {
    // Con un único origen permitido, Laravel responde siempre con ese origen:
    // el navegador rechaza la respuesta porque no coincide con el solicitante.
    $permitido = preflight('/login', 'POST', 'http://sitio-ajeno.test')
        ->headers->get('Access-Control-Allow-Origin');

    expect($permitido)->not->toBe('http://sitio-ajeno.test');
});
