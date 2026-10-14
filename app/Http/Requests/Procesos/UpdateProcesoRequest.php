<?php

namespace App\Http\Requests\Procesos;

use App\Http\Requests\Concerns\GeneraSlug;
use App\Models\Proceso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

/**
 * Actualización parcial. departamento_id no se acepta: un proceso no cambia
 * de departamento (sus matrices pertenecen a esa unidad).
 */
class UpdateProcesoRequest extends FormRequest
{
    use GeneraSlug;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->proceso());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $proceso = $this->proceso();

        return [
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes', 'required', 'string', 'max:255', self::FORMATO_SLUG,
                Rule::unique('procesos', 'slug')
                    ->where('departamento_id', $proceso->departamento_id)
                    ->ignore($proceso->id),
            ],
            'descripcion' => ['sometimes', 'nullable', 'string'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * En edición el slug solo cambia si se envía explícitamente.
     */
    protected function prepareForValidation(): void {}

    private function proceso(): Proceso
    {
        $proceso = $this->route('proceso');

        if (! $proceso instanceof Proceso) {
            throw new LogicException('UpdateProcesoRequest requiere el parámetro de ruta {proceso}.');
        }

        return $proceso;
    }
}
