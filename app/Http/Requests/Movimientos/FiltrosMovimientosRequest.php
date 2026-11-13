<?php

namespace App\Http\Requests\Movimientos;

use App\Models\Auditoria;
use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /api/movimientos. Los filtros solo acotan: el alcance por departamento
 * lo impone siempre Auditoria::movimientosVisiblesPara(), así que filtrar por
 * un departamento, proceso o edición ajenos devuelve una lista vacía.
 */
class FiltrosMovimientosRequest extends FormRequest
{
    public const POR_PAGINA = 50;

    public function authorize(): bool
    {
        return $this->user()->can('verMovimientos', Auditoria::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'desde' => ['sometimes', 'date_format:Y-m-d'],
            'hasta' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'usuario_id' => ['sometimes', 'integer'],
            'departamento_id' => ['sometimes', 'integer'],
            'proceso_id' => ['sometimes', 'integer'],
            'matriz_id' => ['sometimes', 'integer'],
            'evento' => ['sometimes', 'string', 'max:40'],
            'entidad' => ['sometimes', 'string', 'max:60'],
            'per_page' => ['sometimes', 'integer', 'min:10', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['desde' => 'fecha inicial', 'hasta' => 'fecha final'];
    }
}
