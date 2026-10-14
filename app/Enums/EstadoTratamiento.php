<?php

namespace App\Enums;

/**
 * Estado del tratamiento de un riesgo (RF-4). En ediciones vigentes es lo
 * único que cambia, mediante el seguimiento (Fase 6).
 */
enum EstadoTratamiento: string
{
    case Pendiente = 'pendiente';
    case EnProgreso = 'en_progreso';
    case Cumplido = 'cumplido';
}
