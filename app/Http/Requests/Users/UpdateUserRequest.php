<?php

namespace App\Http\Requests\Users;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Actualización parcial: solo se validan y cambian los campos enviados.
 */
class UpdateUserRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules, UserRules;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->usuario());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            ...$this->profileRules($this->usuario()->id),
            'password' => $this->passwordRules(),
            ...$this->asignacionRules(),
            'activo' => ['boolean'],
        ];

        return collect($rules)
            ->map(fn (array $reglas) => ['sometimes', ...$reglas])
            ->all();
    }

    /**
     * Un administrador no puede desactivarse ni quitarse el rol a sí mismo:
     * así el sistema nunca se queda sin nadie capaz de gestionar usuarios.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->usuario()->isNot($this->user())) {
                    return;
                }

                if ($this->has('activo') && ! $this->boolean('activo')) {
                    $validator->errors()->add('activo', 'No puedes desactivar tu propia cuenta.');
                }

                if ($this->has('role_id') && (int) $this->input('role_id') !== Role::where('slug', 'administrador')->value('id')) {
                    $validator->errors()->add('role_id', 'No puedes quitarte el rol de Administrador.');
                }
            },
        ];
    }

    private function usuario(): User
    {
        return $this->route('user');
    }
}
