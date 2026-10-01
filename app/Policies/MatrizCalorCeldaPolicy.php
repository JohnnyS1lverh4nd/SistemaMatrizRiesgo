<?php

namespace App\Policies;

use App\Models\MatrizCalorCelda;
use App\Models\User;

class MatrizCalorCeldaPolicy
{
    /**
     * El admin tiene paso libre, EXCEPTO para crear o eliminar celdas:
     * la matriz de calor siempre debe tener exactamente 16 combinaciones
     * (probabilidad x impacto), así que ni siquiera el Administrador
     * puede romper esa garantía desde la API.
     */
    public function before(User $user, string $ability): ?bool
    {
        if (in_array($ability, ['create', 'delete', 'forceDelete'], true)) {
            return null;
        }

        return $user->role?->slug === 'administrador' ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MatrizCalorCelda $matrizCalorCelda): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, MatrizCalorCelda $matrizCalorCelda): bool
    {
        return false;
    }

    public function delete(User $user, MatrizCalorCelda $matrizCalorCelda): bool
    {
        return false;
    }

    public function restore(User $user, MatrizCalorCelda $matrizCalorCelda): bool
    {
        return false;
    }

    public function forceDelete(User $user, MatrizCalorCelda $matrizCalorCelda): bool
    {
        return false;
    }
}