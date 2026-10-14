<?php

namespace App\Http\Requests\Matrices;

use App\Models\Matriz;
use App\Models\Proceso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use LogicException;

/**
 * POST /api/procesos/{proceso}/matrices: nueva edición en borrador.
 */
class StoreMatrizRequest extends FormRequest
{
    use MatrizRules;

    public function authorize(): bool
    {
        return $this->user()->can('create', [Matriz::class, $this->proceso()]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->reglasEdicion($this->proceso()->id);
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $proceso = $this->proceso();

                if (! $proceso->activo || ! $proceso->departamento->activo) {
                    $validator->errors()->add('proceso', 'No se pueden crear matrices de un proceso o departamento inactivo.');
                }
            },
        ];
    }

    public function proceso(): Proceso
    {
        $proceso = $this->route('proceso');

        if (! $proceso instanceof Proceso) {
            throw new LogicException('StoreMatrizRequest requiere el parámetro de ruta {proceso}.');
        }

        return $proceso;
    }
}
