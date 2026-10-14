<?php

namespace App\Policies;

use App\Models\Proceso;
use App\Models\User;

/**
 * Un proceso se ve si se puede ver su departamento (DepartamentoPolicy::view):
 * la regla de alcance vive en un solo sitio. Alta y edición: solo Administrador.
 */
class ProcesoPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->role?->slug === 'administrador' ? true : null;
    }

    public function view(User $user, Proceso $proceso): bool
    {
        return $user->can('view', $proceso->departamento);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Proceso $proceso): bool
    {
        return false;
    }
}
