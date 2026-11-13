<?php

namespace App\Policies;

use App\Models\Auditoria;
use App\Models\User;

/**
 * El registro completo lo consultan el Administrador y el Auditor (decisión
 * oct. 2026). El historial de un riesgo o edición concreta lo ve quien puede
 * ver ese registro (ver AuditoriaController).
 *
 * Historial de movimientos por área (decisión nov. 2026): los Encargados en
 * sus departamentos y el Administrador en todos. Auditor y Dirección no.
 */
class AuditoriaPolicy
{
    private const ROLES_HISTORIAL = ['administrador', 'encargado-departamento', 'encargado-seguimiento'];

    public function viewAny(User $user): bool
    {
        return in_array($user->role?->slug, ['administrador', 'auditor'], true);
    }

    public function verMovimientos(User $user): bool
    {
        return in_array($user->role?->slug, self::ROLES_HISTORIAL, true);
    }

    /**
     * Un movimiento concreto: con área, y de un departamento a su alcance.
     * El controlador ya responde 404 a los registros sin área.
     */
    public function verMovimiento(User $user, Auditoria $registro): bool
    {
        if (! $this->verMovimientos($user) || $registro->departamento_id === null) {
            return false;
        }

        return $user->role?->slug === 'administrador'
            || $user->departamentos->contains($registro->departamento_id);
    }
}
