<?php

namespace App\Policies;

use App\Models\Evidencia;
use App\Models\User;

/**
 * Una evidencia se descarga si se puede ver el riesgo al que pertenece
 * (misma regla de alcance por departamento).
 */
class EvidenciaPolicy
{
    public function view(User $user, Evidencia $evidencia): bool
    {
        return $user->can('view', $evidencia->seguimiento->riesgo);
    }
}
