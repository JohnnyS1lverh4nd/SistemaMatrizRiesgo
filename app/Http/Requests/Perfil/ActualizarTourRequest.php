<?php

namespace App\Http\Requests\Perfil;

use App\Enums\EstadoTour;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PUT /api/perfil/tour: cómo terminó el recorrido guiado el usuario de la
 * sesión. No hay forma de indicar otro usuario.
 */
class ActualizarTourRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'estado' => ['required', 'string', Rule::enum(EstadoTour::class)],
            'user_id' => ['prohibited'],
            'id' => ['prohibited'],
        ];
    }
}
