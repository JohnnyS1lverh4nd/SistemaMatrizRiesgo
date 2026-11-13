<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Completa el área (departamento, proceso, edición) de los registros de
     * auditoría anteriores a nov. 2026, para que aparezcan en el historial de
     * movimientos. Autorizado por el responsable del proyecto (2026-11-12).
     *
     * Solo rellena las columnas nuevas cuando están vacías: no modifica el
     * evento, el usuario, la fecha ni los valores registrados. Usa el query
     * builder porque el modelo Auditoria prohíbe cualquier actualización.
     *
     * Es autocontenida (no usa los modelos de la aplicación) para que siga
     * funcionando aunque el código cambie después.
     */
    public function up(): void
    {
        // Mapas id -> padre, incluidos los eliminados lógicamente (las
        // consultas del query builder no aplican SoftDeletes).
        $departamentoDeProceso = DB::table('procesos')->pluck('departamento_id', 'id');
        $procesoDeMatriz = DB::table('matrices')->pluck('proceso_id', 'id');
        $matrizDeRiesgo = DB::table('riesgos')->pluck('matriz_id', 'id');
        $riesgoDeSeguimiento = DB::table('seguimientos')->pluck('riesgo_id', 'id');
        $seguimientoDeEvidencia = DB::table('evidencias')->pluck('seguimiento_id', 'id');

        $contextoDeMatriz = function (?int $matrizId) use ($procesoDeMatriz, $departamentoDeProceso): ?array {
            $procesoId = $matrizId !== null ? $procesoDeMatriz[$matrizId] ?? null : null;

            return $procesoId === null ? null : [
                'departamento_id' => $departamentoDeProceso[$procesoId] ?? null,
                'proceso_id' => $procesoId,
                'matriz_id' => $matrizId,
            ];
        };

        DB::table('auditoria')
            ->whereNull('departamento_id')
            ->whereNotNull('entidad_id')
            ->whereIn('entidad', ['departamento', 'proceso', 'matriz', 'riesgo', 'seguimiento', 'evidencia'])
            ->orderBy('id')
            ->chunkById(500, function ($registros) use ($departamentoDeProceso, $matrizDeRiesgo, $riesgoDeSeguimiento, $seguimientoDeEvidencia, $contextoDeMatriz) {
                foreach ($registros as $registro) {
                    $id = (int) $registro->entidad_id;

                    $contexto = match ($registro->entidad) {
                        'departamento' => ['departamento_id' => $id],
                        'proceso' => isset($departamentoDeProceso[$id])
                            ? ['departamento_id' => $departamentoDeProceso[$id], 'proceso_id' => $id]
                            : null,
                        'matriz' => $contextoDeMatriz($id),
                        'riesgo' => $contextoDeMatriz($matrizDeRiesgo[$id] ?? null),
                        'seguimiento' => $contextoDeMatriz($matrizDeRiesgo[$riesgoDeSeguimiento[$id] ?? 0] ?? null),
                        'evidencia' => $contextoDeMatriz($matrizDeRiesgo[$riesgoDeSeguimiento[$seguimientoDeEvidencia[$id] ?? 0] ?? 0] ?? null),
                        default => null,
                    };

                    if ($contexto !== null && ($contexto['departamento_id'] ?? null) !== null) {
                        DB::table('auditoria')->where('id', $registro->id)->update($contexto);
                    }
                }
            });
    }

    /**
     * Sin reversión de datos: al deshacer la migración anterior desaparecen
     * las columnas completas.
     */
    public function down(): void {}
};
