<?php

namespace App\Enums;

/**
 * Estado del recorrido guiado de un usuario. Sin valor (null) = nunca lo
 * inició ni lo omitió: la aplicación le invita a hacerlo al entrar.
 */
enum EstadoTour: string
{
    case Completado = 'completado';
    case Omitido = 'omitido';
}
