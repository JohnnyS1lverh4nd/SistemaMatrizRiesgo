<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;

/**
 * Respuesta idéntica para cualquier solicitud de enlace de restablecimiento,
 * se haya enviado el correo o no (email desconocido, solicitud limitada).
 * Así la API no revela qué correos tienen cuenta en el sistema.
 */
class PasswordResetLinkRequestedResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    public const MESSAGE = 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.';

    public function toResponse($request): JsonResponse
    {
        return new JsonResponse(['message' => self::MESSAGE]);
    }
}
