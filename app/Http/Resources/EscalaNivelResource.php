<?php

namespace App\Http\Resources;

use App\Models\EscalaNivel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EscalaNivel */
class EscalaNivelResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo->value,
            'valor' => $this->valor,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
        ];
    }
}
