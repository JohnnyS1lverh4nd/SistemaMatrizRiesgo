<?php

namespace App\Policies;

use App\Models\Departamento;
use App\Models\User;

class DepartamentoPolicy
{
    /**
     * Se ejecuta antes que cualquier otro método de esta policy.
     * Si el Administrador siempre puede todo, lo resolvemos aquí
     * una sola vez en lugar de repetir el chequeo en cada método.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->role?->slug === 'administrador') {
            return true;
        }

        return null; // null = "sigue evaluando el método específico abajo"
    }

    /**
     * Ver el listado de departamentos.
     * Cualquier rol autenticado puede listar; el filtrado de
     * "solo los suyos" para el Encargado se hace en el controlador,
     * no aquí (view() decide sobre UN registro puntual).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Ver un departamento específico.
     */
    public function view(User $user, Departamento $departamento): bool
    {
        // Auditor y Dirección/Gerencia: solo consulta, pero de todo.
        if (in_array($user->role?->slug, ['auditor', 'direccion'], true)) {
            return true;
        }

        // Encargado de Departamento/Proceso y Encargado de Seguimiento:
        // solo si están asignados a ESE departamento puntual.
        return $user->departamentos->contains($departamento->id);
    }

    /**
     * Crear, actualizar o eliminar departamentos: solo Administrador
     * (ya resuelto en before() como true; si llega aquí, es que
     * el usuario NO es admin, así que se niega).
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Departamento $departamento): bool
    {
        return false;
    }

    public function delete(User $user, Departamento $departamento): bool
    {
        return false;
    }

    public function restore(User $user, Departamento $departamento): bool
    {
        return false;
    }

    public function forceDelete(User $user, Departamento $departamento): bool
    {
        return false;
    }
}