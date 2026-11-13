<?php

namespace App\Http\Resources;

use App\Models\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un movimiento del historial por área. A diferencia de AuditoriaResource
 * (Administrador y Auditor), no expone la IP ni el navegador: el encargado
 * necesita saber quién hizo qué y dónde, no desde qué equipo.
 *
 * Los valores anteriores y nuevos solo van en el detalle (conDetalle()), para
 * que el listado sea ligero.
 *
 * @mixin Auditoria
 */
class MovimientoResource extends JsonResource
{
    private bool $detalle = false;

    public function conDetalle(): static
    {
        $this->detalle = true;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fecha' => $this->created_at->toIso8601String(),
            'evento' => $this->evento,
            'entidad' => $this->entidad,
            'entidad_id' => $this->entidad_id,
            'descripcion' => $this->descripcion,
            'usuario' => $this->usuario ? ['id' => $this->usuario->id, 'name' => $this->usuario->name] : null,
            'departamento' => $this->departamento ? ['id' => $this->departamento->id, 'nombre' => $this->departamento->nombre] : null,
            'proceso' => $this->proceso ? ['id' => $this->proceso->id, 'nombre' => $this->proceso->nombre] : null,
            'matriz' => $this->matriz ? [
                'id' => $this->matriz->id,
                'nombre' => $this->matriz->nombre,
                'descartada' => $this->matriz->trashed(),
            ] : null,
            'valores_anteriores' => $this->when($this->detalle, fn () => $this->valores_anteriores),
            'valores_nuevos' => $this->when($this->detalle, fn () => $this->valores_nuevos),
        ];
    }
}
