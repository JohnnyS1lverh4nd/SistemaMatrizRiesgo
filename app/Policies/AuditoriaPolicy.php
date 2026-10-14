<?php

namespace App\Policies;

use App\Models\User;

/**
 * El registro completo lo consultan el Administrador y el Auditor (decisión
 * oct. 2026). El historial de un riesgo o edición concreta lo ve quien puede
 * ver ese registro (ver AuditoriaController).
 */
class AuditoriaPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role?->slug, ['administrador', 'auditor'], true);
    }
}
