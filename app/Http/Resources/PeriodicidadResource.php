<?php

namespace App\Http\Resources;

use App\Models\Periodicidad;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Periodicidad */
class PeriodicidadResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'slug' => $this->slug,
            'frecuencia_dias' => $this->frecuencia_dias,
            'activo' => $this->activo,
        ];
    }
}
