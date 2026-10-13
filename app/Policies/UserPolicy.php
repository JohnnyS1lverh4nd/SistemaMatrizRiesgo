<?php

namespace App\Policies;

use App\Models\User;

/**
 * Gestión de usuarios (RF-8): exclusiva del Administrador.
 * No existe delete: los usuarios se desactivan (update con activo=false).
 */
class UserPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->role?->slug === 'administrador' ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, User $model): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, User $model): bool
    {
        return false;
    }
}
