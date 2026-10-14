<?php

namespace App\Http\Requests\Procesos;

use App\Http\Requests\Concerns\GeneraSlug;
use App\Models\Departamento;
use App\Models\Proceso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LogicException;

/**
 * POST /api/departamentos/{departamento}/procesos. El departamento sale de la
 * URL, nunca del cuerpo de la petición.
 */
class StoreProcesoRequest extends FormRequest
{
    use GeneraSlug;

    public function authorize(): bool
    {
        return $this->user()->can('create', Proceso::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            // Único dentro del departamento: el mismo proceso existe en varias unidades.
            'slug' => [
                'required', 'string', 'max:255', self::FORMATO_SLUG,
                Rule::unique('procesos', 'slug')->where('departamento_id', $this->departamento()->id),
            ],
            'descripcion' => ['nullable', 'string'],
            'activo' => ['boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->departamento()->activo) {
                    $validator->errors()->add('departamento', 'No se pueden crear procesos en un departamento inactivo.');
                }
            },
        ];
    }

    public function departamento(): Departamento
    {
        $departamento = $this->route('departamento');

        if (! $departamento instanceof Departamento) {
            throw new LogicException('StoreProcesoRequest requiere el parámetro de ruta {departamento}.');
        }

        return $departamento;
    }
}
