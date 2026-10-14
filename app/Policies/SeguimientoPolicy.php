<?php

namespace App\Policies;

use App\Enums\EstadoMatriz;
use App\Models\Riesgo;
use App\Models\Seguimiento;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Seguimiento solo en ediciones vigentes (en borrador aún no hay nada que
 * seguir; las archivadas son inmutables). Sin before(): las reglas de estado
 * aplican también al Administrador.
 */
class SeguimientoPolicy
{
    public function create(User $user, Riesgo $riesgo): Response
    {
        return $this->registrar($user, $riesgo, 'Solo se registra seguimiento en ediciones vigentes.');
    }

    public function anular(User $user, Seguimiento $seguimiento): Response
    {
        if ($seguimiento->estaAnulado()) {
            return Response::deny('Este seguimiento ya fue anulado.');
        }

        return $this->registrar($user, $seguimiento->riesgo, 'Solo se anulan seguimientos de ediciones vigentes.');
    }

    private function registrar(User $user, Riesgo $riesgo, string $mensajeEstado): Response
    {
        $matriz = $riesgo->matriz;

        if (! $user->puedeRegistrarSeguimientoEn($matriz->proceso->departamento)) {
            return Response::deny();
        }

        return $matriz->estado === EstadoMatriz::Vigente ? Response::allow() : Response::deny($mensajeEstado);
    }
}
