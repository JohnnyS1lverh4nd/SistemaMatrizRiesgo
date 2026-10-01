<?php

namespace App\Policies;

use App\Models\FactorRiesgo;
use App\Models\User;

class FactorRiesgoPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->role?->slug === 'administrador' ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, FactorRiesgo $factorRiesgo): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, FactorRiesgo $factorRiesgo): bool
    {
        return false;
    }

    public function delete(User $user, FactorRiesgo $factorRiesgo): bool
    {
        return false;
    }

    public function restore(User $user, FactorRiesgo $factorRiesgo): bool
    {
        return false;
    }

    public function forceDelete(User $user, FactorRiesgo $factorRiesgo): bool
    {
        return false;
    }
}