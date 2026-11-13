<?php

namespace App\Http\Requests\Perfil;

use App\Concerns\ProfileValidationRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PUT /api/perfil: el usuario autenticado cambia su propio nombre.
 *
 * Decisión del responsable (nov. 2026): el correo (es el usuario de acceso),
 * el rol, los departamentos y el estado de la cuenta los gestiona el
 * Administrador en Usuarios; la contraseña, PUT /user/password. Enviarlos
 * aquí es un error explícito (422), no algo que se ignore en silencio.
 */
class ActualizarPerfilRequest extends FormRequest
{
    use ProfileValidationRules;

    /** Cualquier usuario autenticado y activo (lo garantizan las rutas). */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => $this->nameRules(),

            'id' => ['prohibited'],
            'email' => ['prohibited'],
            'role_id' => ['prohibited'],
            'departamento_ids' => ['prohibited'],
            'activo' => ['prohibited'],
            'password' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $administrador = 'Este dato lo gestiona el Administrador del sistema.';

        return [
            'email.prohibited' => $administrador,
            'role_id.prohibited' => $administrador,
            'departamento_ids.prohibited' => $administrador,
            'activo.prohibited' => $administrador,
            'password.prohibited' => 'La contraseña se cambia en Seguridad de la cuenta.',
        ];
    }
}
