<?php

namespace App\Http\Requests\Departamentos;

use App\Http\Requests\Concerns\GeneraSlug;
use App\Models\Departamento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

/**
 * Actualización parcial. Para retirar un departamento: { activo: false }.
 */
class UpdateDepartamentoRequest extends FormRequest
{
    use GeneraSlug;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->departamento());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes', 'required', 'string', 'max:255', self::FORMATO_SLUG,
                Rule::unique('departamentos', 'slug')->ignore($this->departamento()->id),
            ],
            'direccion' => ['sometimes', 'nullable', 'string', 'max:255'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * En edición el slug no se regenera al cambiar el nombre: solo cambia si
     * se envía explícitamente.
     */
    protected function prepareForValidation(): void {}

    private function departamento(): Departamento
    {
        $departamento = $this->route('departamento');

        if (! $departamento instanceof Departamento) {
            throw new LogicException('UpdateDepartamentoRequest requiere el parámetro de ruta {departamento}.');
        }

        return $departamento;
    }
}
