<?php

namespace App\Http\Resources;

use App\Models\Riesgo;
use App\Models\Seguimiento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Riesgo */
class RiesgoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $nivelEfectivo = $this->nivelEfectivo();

        return [
            'id' => $this->id,
            'matriz_id' => $this->matriz_id,
            'riesgo_origen_id' => $this->riesgo_origen_id,
            'orden' => $this->orden,
            'actividad' => $this->actividad,
            'descripcion' => $this->descripcion,
            'factores' => $this->factores->map(fn ($factor) => ['id' => $factor->id, 'nombre' => $factor->nombre])->values(),
            'causas' => $this->causas,
            'control_actual' => $this->control_actual,
            'probabilidad' => $this->probabilidad,
            'impacto' => $this->impacto,
            'nivel_calculado' => $this->nivelCalculado ? new NivelRiesgoResource($this->nivelCalculado) : null,
            'nivel_ajustado' => $this->nivelAjustado ? new NivelRiesgoResource($this->nivelAjustado) : null,
            'justificacion_ajuste' => $this->justificacion_ajuste,
            // El que rige: ajustado si existe, si no el calculado.
            'nivel' => $nivelEfectivo ? new NivelRiesgoResource($nivelEfectivo) : null,
            'sintoma' => $this->sintoma,
            'tratamiento' => $this->tratamiento,
            'responsable' => $this->responsable,
            'periodicidad' => $this->periodicidad ? ['id' => $this->periodicidad->id, 'nombre' => $this->periodicidad->nombre] : null,
            'estado_tratamiento' => $this->estado_tratamiento->value,
            // Para que el frontend muestre u oculte acciones; la API vuelve a comprobarlo.
            'permisos' => [
                'editar' => $request->user()?->can('update', $this->resource) ?? false,
                'eliminar' => $request->user()?->can('delete', $this->resource) ?? false,
                'registrar_seguimiento' => $request->user()?->can('create', [Seguimiento::class, $this->resource]) ?? false,
            ],
        ];
    }
}
