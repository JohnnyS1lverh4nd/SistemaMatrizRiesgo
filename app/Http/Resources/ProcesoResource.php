<?php

namespace App\Http\Resources;

use App\Models\Matriz;
use App\Models\Proceso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Proceso */
class ProcesoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'departamento_id' => $this->departamento_id,
            'nombre' => $this->nombre,
            'slug' => $this->slug,
            'descripcion' => $this->descripcion,
            'activo' => $this->activo,
            // Para que el frontend muestre u oculte acciones; la API vuelve a comprobarlo.
            'permisos' => [
                'crear_matriz' => $request->user()?->can('create', [Matriz::class, $this->resource]) ?? false,
            ],
        ];
    }
}
