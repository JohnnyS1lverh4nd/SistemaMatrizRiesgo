<?php

namespace App\Policies;

use App\Models\Matriz;
use App\Models\Riesgo;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Los permisos sobre un riesgo son los de su edición: quien puede ver la
 * matriz ve sus riesgos; quien puede editar la matriz (borrador, en su
 * departamento) gestiona sus riesgos. Una sola regla en MatrizPolicy.
 */
class RiesgoPolicy
{
    public function view(User $user, Riesgo $riesgo): bool
    {
        return $user->can('view', $riesgo->matriz);
    }

    public function create(User $user, Matriz $matriz): Response
    {
        return $user->can('update', $matriz)
            ? Response::allow()
            : Response::deny('Solo se agregan riesgos a ediciones en borrador de su departamento.');
    }

    public function update(User $user, Riesgo $riesgo): Response
    {
        return $user->can('update', $riesgo->matriz)
            ? Response::allow()
            : Response::deny('Solo se modifican riesgos de ediciones en borrador de su departamento.');
    }

    public function delete(User $user, Riesgo $riesgo): Response
    {
        return $this->update($user, $riesgo);
    }
}
