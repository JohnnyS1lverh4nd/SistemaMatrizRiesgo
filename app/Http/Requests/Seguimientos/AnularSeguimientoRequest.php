<?php

namespace App\Http\Requests\Seguimientos;

use App\Models\Seguimiento;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

/**
 * POST /api/seguimientos/{seguimiento}/anular: el registro sigue visible en el
 * histórico, marcado como anulado con su motivo.
 */
class AnularSeguimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('anular', $this->seguimiento());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'max:2000'],
        ];
    }

    public function seguimiento(): Seguimiento
    {
        $seguimiento = $this->route('seguimiento');

        if (! $seguimiento instanceof Seguimiento) {
            throw new LogicException('AnularSeguimientoRequest requiere el parámetro de ruta {seguimiento}.');
        }

        return $seguimiento;
    }
}
