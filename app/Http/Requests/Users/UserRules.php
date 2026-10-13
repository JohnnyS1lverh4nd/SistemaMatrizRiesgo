<?php

namespace App\Http\Requests\Users;

use Illuminate\Validation\Rule;

/**
 * Reglas compartidas por StoreUserRequest y UpdateUserRequest.
 */
trait UserRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function asignacionRules(): array
    {
        return [
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
            'departamento_ids' => ['array'],
            'departamento_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('departamentos', 'id')->where('activo', true),
            ],
        ];
    }

    /**
     * Fortify guarda los emails en minúsculas al autenticar
     * (lowercase_usernames): se normalizan igual al crearlos/editarlos.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }
}
