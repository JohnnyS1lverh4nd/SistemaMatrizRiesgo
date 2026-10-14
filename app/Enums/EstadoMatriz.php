<?php

namespace App\Enums;

/**
 * Ciclo de vida de una edición de matriz: borrador -> vigente -> archivada.
 * No hay vuelta atrás: para reevaluar se clona una edición nueva.
 */
enum EstadoMatriz: string
{
    case Borrador = 'borrador';
    case Vigente = 'vigente';
    case Archivada = 'archivada';

    /** Transiciones permitidas desde este estado. */
    public function puedePasarA(self $destino): bool
    {
        return match ($this) {
            self::Borrador => $destino === self::Vigente,
            self::Vigente => $destino === self::Archivada,
            self::Archivada => false,
        };
    }
}
