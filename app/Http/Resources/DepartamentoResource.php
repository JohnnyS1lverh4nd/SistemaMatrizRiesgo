<?php

namespace App\Http\Resources;

use App\Models\Departamento;
use App\Models\Proceso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Departamento */
class DepartamentoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'slug' => $this->slug,
            'direccion' => $this->direccion,
            'activo' => $this->activo,
            // Para que el frontend muestre "Nueva matriz"; la API vuelve a comprobarlo.
            'permisos' => [
                'crear_proceso' => $request->user()?->can('create', [Proceso::class, $this->resource]) ?? false,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
