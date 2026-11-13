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
     * $contexto: área afectada (departamento_id, proceso_id, matriz_id) para
     * el historial de movimientos. Vacío en catálogos, usuarios y sesiones.
     *
     * @param  array<string, mixed>|null  $antes
     * @param  array<string, mixed>|null  $despues
     * @param  array{departamento_id?: int|null, proceso_id?: int|null, matriz_id?: int|null}  $contexto
     */
    public static function registrar(
        string $evento,
        ?string $entidad = null,
        ?int $entidadId = null,
        ?array $antes = null,
        ?array $despues = null,
        ?string $descripcion = null,
        ?User $usuario = null,
        array $contexto = [],
    ): void {
        $peticion = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();
        $usuario ??= Auth::user();

        (new Auditoria)->forceFill([
            'user_id' => $usuario instanceof User ? $usuario->id : null,
            'evento' => $evento,
            'entidad' => $entidad,
            'entidad_id' => $entidadId,
            'departamento_id' => $contexto['departamento_id'] ?? null,
            'proceso_id' => $contexto['proceso_id'] ?? null,
            'matriz_id' => $contexto['matriz_id'] ?? null,
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
     * @param  array{departamento_id?: int|null, proceso_id?: int|null, matriz_id?: int|null}  $contexto
     */
    public static function registrarRelacion(string $entidad, int $entidadId, string $campo, array $antes, array $despues, array $contexto = []): void
    {
        sort($antes);
        sort($despues);

        if ($antes === $despues) {
            return;
        }

        self::registrar('actualizado', $entidad, $entidadId, [$campo => $antes], [$campo => $despues], contexto: $contexto);
    }
}
