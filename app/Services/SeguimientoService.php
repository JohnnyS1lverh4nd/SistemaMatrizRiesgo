<?php

namespace App\Services;

use App\Enums\EstadoTratamiento;
use App\Models\Evidencia;
use App\Models\Riesgo;
use App\Models\Seguimiento;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Registro y anulación de seguimientos. Cada operación es atómica: el
 * seguimiento, el cambio de estado del tratamiento y las evidencias se
 * guardan juntos o no se guarda nada.
 */
class SeguimientoService
{
    /** Disco privado (storage/app/private): nada es accesible por URL directa. */
    public const DISCO = 'local';

    /**
     * @param  array{fecha: string, descripcion: string, estado_tratamiento?: string|null}  $datos
     * @param  array<int, UploadedFile>  $archivos
     */
    public function registrar(Riesgo $riesgo, User $autor, array $datos, array $archivos = []): Seguimiento
    {
        $guardados = [];

        try {
            return DB::transaction(function () use ($riesgo, $autor, $datos, $archivos, &$guardados) {
                // Serializa registros simultáneos sobre el mismo riesgo.
                $riesgo = Riesgo::whereKey($riesgo->id)->lockForUpdate()->firstOrFail();

                $anterior = $riesgo->estado_tratamiento;
                $nuevo = isset($datos['estado_tratamiento']) ? EstadoTratamiento::from($datos['estado_tratamiento']) : null;
                $cambia = $nuevo !== null && $nuevo !== $anterior;

                $seguimiento = (new Seguimiento)->forceFill([
                    'riesgo_id' => $riesgo->id,
                    'user_id' => $autor->id,
                    'fecha' => $datos['fecha'],
                    'descripcion' => $datos['descripcion'],
                    'estado_tratamiento_anterior' => $cambia ? $anterior : null,
                    'estado_tratamiento_nuevo' => $cambia ? $nuevo : null,
                ]);
                $seguimiento->save();

                if ($cambia) {
                    $riesgo->estado_tratamiento = $nuevo;
                    $riesgo->save();
                }

                foreach ($archivos as $archivo) {
                    // Nombre aleatorio en disco; el original solo se guarda como dato.
                    $ruta = $archivo->store("evidencias/{$riesgo->id}", self::DISCO);
                    $guardados[] = $ruta;

                    (new Evidencia)->forceFill([
                        'seguimiento_id' => $seguimiento->id,
                        'nombre_original' => $archivo->getClientOriginalName(),
                        'ruta' => $ruta,
                        'mime' => $archivo->getMimeType() ?? 'application/octet-stream',
                        'tamano' => $archivo->getSize(),
                        'sha256' => hash_file('sha256', $archivo->getRealPath()),
                    ])->save();
                }

                return $seguimiento;
            });
        } catch (Throwable $e) {
            // Si la transacción falla, no deja archivos huérfanos en disco.
            Storage::disk(self::DISCO)->delete($guardados);

            throw $e;
        }
    }

    /**
     * Marca el registro como anulado. Si fue el último cambio de estado del
     * tratamiento, el riesgo vuelve al estado anterior a ese cambio.
     */
    public function anular(Seguimiento $seguimiento, User $autor, string $motivo): Seguimiento
    {
        return DB::transaction(function () use ($seguimiento, $autor, $motivo) {
            $riesgo = Riesgo::whereKey($seguimiento->riesgo_id)->lockForUpdate()->firstOrFail();

            $seguimiento->forceFill([
                'anulado_at' => now(),
                'anulado_por_id' => $autor->id,
                'motivo_anulacion' => $motivo,
            ])->save();

            $huboCambioPosterior = Seguimiento::where('riesgo_id', $riesgo->id)
                ->whereNull('anulado_at')
                ->whereNotNull('estado_tratamiento_nuevo')
                ->where('id', '>', $seguimiento->id)
                ->exists();

            if ($seguimiento->estado_tratamiento_anterior !== null && ! $huboCambioPosterior) {
                $riesgo->estado_tratamiento = $seguimiento->estado_tratamiento_anterior;
                $riesgo->save();
            }

            return $seguimiento;
        });
    }
}
