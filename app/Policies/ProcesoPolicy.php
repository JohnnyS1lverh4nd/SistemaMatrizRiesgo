<?php

namespace App\Policies;

use App\Models\Departamento;
use App\Models\Proceso;
use App\Models\User;

/**
 * Un proceso se ve si se puede ver su departamento (DepartamentoPolicy::view):
 * la regla de alcance vive en un solo sitio.
 *
 * Alta (= nueva matriz, RF-2): Administrador en todos los departamentos;
 * Encargado de Departamento en los suyos. Edición: Administrador en todos;
 * el Encargado solo en los procesos que él creó y mientras siga asignado
 * al departamento.
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

    public function create(User $user, Departamento $departamento): bool
    {
        return $user->puedeGestionarMatricesDe($departamento);
    }

    public function update(User $user, Proceso $proceso): bool
    {
        return $proceso->creado_por_id === $user->id
            && $user->puedeGestionarMatricesDe($proceso->departamento);
    }
}
