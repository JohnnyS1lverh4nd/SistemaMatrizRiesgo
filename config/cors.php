<?php

return [

    // Toda ruta que llame el frontend Nuxt (otro origen). Las de Fortify
    // viven fuera de api/*; tests/Feature/CorsTest.php verifica la lista.
    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'login',
        'logout',
        'forgot-password',
        'reset-password',
        'user/password',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:3000'),
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
