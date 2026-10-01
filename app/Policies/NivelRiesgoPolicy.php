<?php

namespace App\Policies;

use App\Models\NivelRiesgo;
use App\Models\User;

class NivelRiesgoPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->role?->slug === 'administrador' ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, NivelRiesgo $nivelRiesgo): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, NivelRiesgo $nivelRiesgo): bool
    {
        return false;
    }

    public function delete(User $user, NivelRiesgo $nivelRiesgo): bool
    {
        return false;
    }

    public function restore(User $user, NivelRiesgo $nivelRiesgo): bool
    {
        return false;
    }

    public function forceDelete(User $user, NivelRiesgo $nivelRiesgo): bool
    {
        return false;
    }
}