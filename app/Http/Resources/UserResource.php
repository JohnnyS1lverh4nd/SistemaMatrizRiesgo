<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Lista explícita de campos: nada sensible (password, tokens, 2FA)
     * puede filtrarse aunque se agreguen columnas nuevas a users.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'activo' => $this->activo,
            'role' => $this->whenLoaded('role', fn () => $this->role ? [
                'id' => $this->role->id,
                'nombre' => $this->role->nombre,
                'slug' => $this->role->slug,
            ] : null),
            'departamentos' => $this->whenLoaded('departamentos', fn () => $this->departamentos
                ->map(fn ($departamento) => [
                    'id' => $departamento->id,
                    'nombre' => $departamento->nombre,
                    'slug' => $departamento->slug,
                ])
                ->values()),
        ];
    }
}
