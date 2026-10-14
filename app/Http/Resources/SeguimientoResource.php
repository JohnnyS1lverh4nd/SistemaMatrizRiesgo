<?php

namespace App\Http\Resources;

use App\Models\Evidencia;
use App\Models\Seguimiento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Seguimiento */
class SeguimientoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'riesgo_id' => $this->riesgo_id,
            'fecha' => $this->fecha->toDateString(),
            'descripcion' => $this->descripcion,
            'estado_tratamiento_anterior' => $this->estado_tratamiento_anterior?->value,
            'estado_tratamiento_nuevo' => $this->estado_tratamiento_nuevo?->value,
            'registrado_por' => ['id' => $this->registradoPor->id, 'name' => $this->registradoPor->name],
            'registrado_at' => $this->created_at?->toIso8601String(),
            'anulado' => $this->estaAnulado(),
            'anulado_at' => $this->anulado_at?->toIso8601String(),
            'anulado_por' => $this->anuladoPor ? ['id' => $this->anuladoPor->id, 'name' => $this->anuladoPor->name] : null,
            'motivo_anulacion' => $this->motivo_anulacion,
            // Sin la ruta interna: la descarga pasa por la API con autorización.
            'evidencias' => $this->evidencias->map(fn (Evidencia $evidencia) => [
                'id' => $evidencia->id,
                'nombre' => $evidencia->nombre_original,
                'mime' => $evidencia->mime,
                'tamano' => $evidencia->tamano,
            ])->values(),
            'permisos' => [
                'anular' => $request->user()?->can('anular', $this->resource) ?? false,
            ],
        ];
    }
}
