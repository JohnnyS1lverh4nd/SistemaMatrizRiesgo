<?php

namespace App\Http\Requests\Matrices;

use App\Models\Matriz;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

/**
 * PUT /api/matrices/{matriz}: metadatos de un borrador (actualización parcial).
 */
class UpdateMatrizRequest extends FormRequest
{
    use MatrizRules;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->matriz());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $matriz = $this->matriz();

        return collect($this->reglasEdicion($matriz->proceso_id, $matriz->id))
            ->map(fn (array $reglas) => in_array('prohibited', $reglas, true) ? $reglas : ['sometimes', ...$reglas])
            ->all();
    }

    private function matriz(): Matriz
    {
        $matriz = $this->route('matriz');

        if (! $matriz instanceof Matriz) {
            throw new LogicException('UpdateMatrizRequest requiere el parámetro de ruta {matriz}.');
        }

        return $matriz;
    }
}
