<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * Lista explícita de campos: nada sensible (password, tokens, 2FA)
     * puede filtrarse aunque se agreguen columnas nuevas a users.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'activo' => $this->activo,
            // Fecha de alta de la cuenta (se muestra en Mi perfil).
            'created_at' => $this->created_at?->toIso8601String(),
            // Solo para el propio usuario (GET /api/user, Mi perfil): el estado del
            // recorrido guiado de otros no le interesa al Administrador.
            'tour' => $this->when($request->user()?->id === $this->id, fn () => [
                'estado' => $this->tour_estado?->value,
                'actualizado_at' => $this->tour_actualizado_at?->toIso8601String(),
            ]),
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
