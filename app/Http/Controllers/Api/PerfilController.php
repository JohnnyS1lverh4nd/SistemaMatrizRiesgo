<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Perfil\ActualizarPerfilRequest;
use App\Http\Requests\Perfil\ActualizarTourRequest;
use App\Http\Resources\UserResource;
use App\Models\User;

/**
 * Mi perfil: siempre el usuario de la sesión, nunca uno indicado en la
 * petición. Los datos se consultan con GET /api/user.
 *   PUT /api/perfil        cambia el nombre propio
 *   PUT /api/perfil/tour   estado del recorrido guiado (completado | omitido)
 * La contraseña se cambia con PUT /user/password (Fortify).
 */
class PerfilController extends Controller
{
    public function update(ActualizarPerfilRequest $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        $user->update(['name' => $request->validated('name')]);

        return new UserResource($user->load(['role', 'departamentos' => fn ($query) => $query->orderBy('nombre')]));
    }

    /**
     * Guarda cómo terminó el recorrido guiado. Repetirlo desde Ayuda no
     * necesita borrar nada: al terminar o cerrarlo se vuelve a guardar.
     */
    public function tour(ActualizarTourRequest $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill([
            'tour_estado' => $request->validated('estado'),
            'tour_actualizado_at' => now(),
        ])->save();

        return new UserResource($user->load(['role', 'departamentos' => fn ($query) => $query->orderBy('nombre')]));
    }
}
