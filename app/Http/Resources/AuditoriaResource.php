<?php

namespace App\Http\Resources;

use App\Models\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Auditoria */
class AuditoriaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fecha' => $this->created_at->toIso8601String(),
            'evento' => $this->evento,
            'entidad' => $this->entidad,
            'entidad_id' => $this->entidad_id,
            'usuario' => $this->usuario ? ['id' => $this->usuario->id, 'name' => $this->usuario->name] : null,
            'ip' => $this->ip,
            'user_agent' => $this->user_agent,
            'descripcion' => $this->descripcion,
            'valores_anteriores' => $this->valores_anteriores,
            'valores_nuevos' => $this->valores_nuevos,
        ];
    }
}
