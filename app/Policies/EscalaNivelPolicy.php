<?php

namespace App\Policies;

use App\Models\EscalaNivel;
use App\Models\User;

class EscalaNivelPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->role?->slug === 'administrador' ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, EscalaNivel $escalaNivel): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, EscalaNivel $escalaNivel): bool
    {
        return false;
    }

    public function delete(User $user, EscalaNivel $escalaNivel): bool
    {
        return false;
    }

    public function restore(User $user, EscalaNivel $escalaNivel): bool
    {
        return false;
    }

    public function forceDelete(User $user, EscalaNivel $escalaNivel): bool
    {
        return false;
    }
}