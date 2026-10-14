<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Formulario institucional de la matriz de riesgos
    |--------------------------------------------------------------------------
    |
    | Código y edición del formato oficial que aparece en el encabezado de
    | todas las matrices (y de sus exportaciones a Excel/PDF). Es el mismo
    | para todos los departamentos; si INFOTEP publica una nueva edición del
    | formato, basta con cambiar estas variables de entorno.
    |
    */

    'formulario' => [
        'codigo' => env('MATRIZ_FORMULARIO_CODIGO', 'RT-01-NI-ONA-156'),
        'edicion' => env('MATRIZ_FORMULARIO_EDICION', 'Edición 1'),
    ],

];
