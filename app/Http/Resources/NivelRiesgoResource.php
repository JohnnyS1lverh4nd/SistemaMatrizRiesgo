<?php

namespace App\Http\Resources;

use App\Models\NivelRiesgo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin NivelRiesgo */
class NivelRiesgoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'slug' => $this->slug,
            'color' => $this->color,
            'orden' => $this->orden,
        ];
    }
}
