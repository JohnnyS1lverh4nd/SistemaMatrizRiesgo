<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatrizCalorCeldaResource extends JsonResource
{
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