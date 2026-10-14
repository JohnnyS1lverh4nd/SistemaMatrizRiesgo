<?php

namespace App\Http\Resources;

use App\Models\MatrizCalorCelda;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MatrizCalorCelda */
class MatrizCalorCeldaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'probabilidad' => $this->probabilidad,
            'impacto' => $this->impacto,
            'nivel_riesgo' => new NivelRiesgoResource($this->whenLoaded('nivelRiesgo')),
        ];
    }
}
