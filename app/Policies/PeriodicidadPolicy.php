<?php

namespace App\Policies;

use App\Models\Periodicidad;
use App\Models\User;

class PeriodicidadPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->role?->slug === 'administrador' ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Periodicidad $periodicidad): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Periodicidad $periodicidad): bool
    {
        return false;
    }

    public function delete(User $user, Periodicidad $periodicidad): bool
    {
        return false;
    }

    public function restore(User $user, Periodicidad $periodicidad): bool
    {
        return false;
    }

    public function forceDelete(User $user, Periodicidad $periodicidad): bool
    {
        return false;
    }
}