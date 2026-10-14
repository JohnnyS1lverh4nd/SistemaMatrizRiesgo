<?php

namespace App\Http\Requests\Matrices;

use App\Models\Matriz;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

/**
 * POST /api/matrices/{matriz}/clonar: nueva edición (borrador) del mismo
 * proceso a partir de otra.
 */
class ClonarMatrizRequest extends FormRequest
{
    use MatrizRules;

    public function authorize(): bool
    {
        return $this->user()->can('clonar', $this->origen());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->reglasEdicion($this->origen()->proceso_id);
    }

    private function origen(): Matriz
    {
        $matriz = $this->route('matriz');

        if (! $matriz instanceof Matriz) {
            throw new LogicException('ClonarMatrizRequest requiere el parámetro de ruta {matriz}.');
        }

        return $matriz;
    }
}
