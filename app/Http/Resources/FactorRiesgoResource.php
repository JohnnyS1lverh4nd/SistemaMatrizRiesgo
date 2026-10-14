<?php

namespace App\Http\Resources;

use App\Models\FactorRiesgo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FactorRiesgo */
class FactorRiesgoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'slug' => $this->slug,
            'activo' => $this->activo,
        ];
    }
}
