<?php

/*
 * Nota: las respuestas de /forgot-password y los errores de /reset-password
 * usan mensajes neutros propios (App\Http\Responses\*) para no revelar qué
 * correos están registrados; estas líneas se mantienen por completitud.
 */

return [

    'reset' => 'Tu contraseña ha sido restablecida.',
    'sent' => 'Te hemos enviado por correo el enlace para restablecer tu contraseña.',
    'throttled' => 'Por favor, espera antes de volver a intentarlo.',
    'token' => 'El enlace para restablecer la contraseña no es válido.',
    'user' => 'No encontramos ningún usuario con ese correo electrónico.',

];
