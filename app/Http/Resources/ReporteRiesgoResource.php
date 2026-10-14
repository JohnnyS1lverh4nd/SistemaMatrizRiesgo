<?php

namespace App\Http\Resources;

use App\Models\Riesgo;
use App\Services\Reportes\EstadoSeguimiento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fila del reporte de riesgos: el riesgo con su contexto (departamento,
 * proceso, edición) y el estado de su seguimiento.
 *
 * @mixin Riesgo
 */
class ReporteRiesgoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $nivel = $this->nivelEfectivo();
        $matriz = $this->matriz;
        $proceso = $matriz->proceso;

        return [
            'id' => $this->id,
            'orden' => $this->orden,
            'actividad' => $this->actividad,
            'descripcion' => $this->descripcion,
            'factores' => $this->factores->map(fn ($f) => ['id' => $f->id, 'nombre' => $f->nombre])->values(),
            'probabilidad' => $this->probabilidad,
            'impacto' => $this->impacto,
            'nivel' => $nivel ? new NivelRiesgoResource($nivel) : null,
            'ajustado' => $this->nivel_ajustado_id !== null,
            'responsable' => $this->responsable,
            'periodicidad' => $this->periodicidad?->nombre,
            'estado_tratamiento' => $this->estado_tratamiento->value,
            'seguimiento' => EstadoSeguimiento::de($this->resource),
            'matriz' => [
                'id' => $matriz->id,
                'nombre' => $matriz->nombre,
                'estado' => $matriz->estado->value,
                'fecha_revision' => $matriz->fecha_revision->toDateString(),
            ],
            'proceso' => ['id' => $proceso->id, 'nombre' => $proceso->nombre],
            'departamento' => [
                'id' => $proceso->departamento->id,
                'nombre' => $proceso->departamento->nombre,
                'direccion' => $proceso->departamento->direccion,
            ],
        ];
    }
}
