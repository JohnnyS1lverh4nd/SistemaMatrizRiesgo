<?php

namespace App\Http\Resources;

use App\Models\Matriz;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Matriz */
class MatrizResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'proceso_id' => $this->proceso_id,
            'nombre' => $this->nombre,
            'fecha_revision' => $this->fecha_revision->toDateString(),
            'estado' => $this->estado->value,
            'edicion_anterior_id' => $this->edicion_anterior_id,
            'publicada_at' => $this->publicada_at?->toIso8601String(),
            'archivada_at' => $this->archivada_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            // Para que el frontend muestre u oculte acciones; la API vuelve a comprobarlo.
            'permisos' => [
                'editar' => $request->user()?->can('update', $this->resource) ?? false,
                'publicar' => $request->user()?->can('publicar', $this->resource) ?? false,
                'archivar' => $request->user()?->can('archivar', $this->resource) ?? false,
                'descartar' => $request->user()?->can('delete', $this->resource) ?? false,
                'clonar' => $request->user()?->can('clonar', $this->resource) ?? false,
            ],
        ];
    }
}
