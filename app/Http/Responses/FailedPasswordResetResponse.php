<?php

namespace App\Http\Responses;

use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedPasswordResetResponse as FailedPasswordResetResponseContract;

/**
 * Mismo error para token inválido, token caducado o email inexistente,
 * para que POST /reset-password no revele qué correos tienen cuenta.
 */
class FailedPasswordResetResponse implements FailedPasswordResetResponseContract
{
    public const MESSAGE = 'El enlace para restablecer la contraseña no es válido o ha expirado.';

    public function toResponse($request): never
    {
        throw ValidationException::withMessages([
            'email' => [self::MESSAGE],
        ]);
    }
}
