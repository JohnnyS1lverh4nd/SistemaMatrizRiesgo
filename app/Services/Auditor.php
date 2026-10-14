<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Único punto de escritura del registro de auditoría. Toma usuario, IP y
 * navegador de la petición en curso (null en consola o colas).
 */
class Auditor
{
    /**
     * @param  array<string, mixed>|null  $antes
     * @param  array<string, mixed>|null  $despues
     */
    public static function registrar(
        string $evento,
        ?string $entidad = null,
        ?int $entidadId = null,
        ?array $antes = null,
        ?array $despues = null,
        ?string $descripcion = null,
        ?User $usuario = null,
    ): void {
        $peticion = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();
        $usuario ??= Auth::user();

        (new Auditoria)->forceFill([
            'user_id' => $usuario instanceof User ? $usuario->id : null,
            'evento' => $evento,
            'entidad' => $entidad,
            'entidad_id' => $entidadId,
            'valores_anteriores' => $antes,
            'valores_nuevos' => $despues,
            'descripcion' => $descripcion !== null ? Str::limit($descripcion, 497) : null,
            'ip' => $peticion?->ip(),
            'user_agent' => $peticion?->userAgent() !== null ? Str::limit((string) $peticion->userAgent(), 497) : null,
        ])->save();
    }

    /**
     * Cambio en una relación muchos a muchos (factores de un riesgo,
     * departamentos de un usuario), que no dispara eventos del modelo.
     *
     * @param  array<int, int>  $antes
     * @param  array<int, int>  $despues
     */
    public static function registrarRelacion(string $entidad, int $entidadId, string $campo, array $antes, array $despues): void
    {
        sort($antes);
        sort($despues);

        if ($antes === $despues) {
            return;
        }

        self::registrar('actualizado', $entidad, $entidadId, [$campo => $antes], [$campo => $despues]);
    }
}
