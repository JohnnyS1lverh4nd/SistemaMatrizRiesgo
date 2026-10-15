<?php

namespace App\Policies;

use App\Enums\EstadoMatriz;
use App\Models\Matriz;
use App\Models\Proceso;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Sin before() para el Administrador: las reglas de estado (borrador /
 * vigente / archivada) se aplican a todos los roles.
 */
class MatrizPolicy
{
    public function view(User $user, Matriz $matriz): bool
    {
        // Misma regla de alcance que el proceso y su departamento.
        return $user->can('view', $matriz->proceso);
    }

    public function create(User $user, Proceso $proceso): bool
    {
        return $user->puedeGestionarMatricesDe($proceso->departamento);
    }

    public function update(User $user, Matriz $matriz): Response
    {
        return $this->gestionarEnEstado($user, $matriz, EstadoMatriz::Borrador, 'Solo se pueden modificar ediciones en borrador.');
    }

    /** Descartar un borrador nunca publicado (eliminación lógica). */
    public function delete(User $user, Matriz $matriz): Response
    {
        return $this->gestionarEnEstado($user, $matriz, EstadoMatriz::Borrador, 'Solo se pueden descartar ediciones en borrador.');
    }

    public function publicar(User $user, Matriz $matriz): Response
    {
        return $this->gestionarEnEstado($user, $matriz, EstadoMatriz::Borrador, 'Solo se pueden publicar ediciones en borrador.');
    }

    public function archivar(User $user, Matriz $matriz): Response
    {
        return $this->gestionarEnEstado($user, $matriz, EstadoMatriz::Vigente, 'Solo se pueden archivar ediciones vigentes.');
    }

    /** Cualquier edición (también archivada) sirve como punto de partida. */
    public function clonar(User $user, Matriz $matriz): bool
    {
        return $user->puedeGestionarMatricesDe($matriz->proceso->departamento);
    }

    /**
     * Carga de matrices desde Excel (RF-7): crea y publica ediciones de una
     * vez, sin pasar por borrador. Reservado al Administrador.
     */
    public function importar(User $user, Proceso $proceso): bool
    {
        return $user->role?->slug === 'administrador';
    }

    private function gestionarEnEstado(User $user, Matriz $matriz, EstadoMatriz $requerido, string $mensaje): Response
    {
        if (! $user->puedeGestionarMatricesDe($matriz->proceso->departamento)) {
            return Response::deny();
        }

        return $matriz->estado === $requerido ? Response::allow() : Response::deny($mensaje);
    }
}
