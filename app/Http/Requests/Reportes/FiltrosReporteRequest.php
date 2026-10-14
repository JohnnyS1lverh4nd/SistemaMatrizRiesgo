<?php

namespace App\Http\Requests\Reportes;

use App\Enums\EstadoTratamiento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros del dashboard, del reporte de riesgos y de la exportación (RF-6):
 * departamento, proceso, factor, nivel, responsable y periodo. El alcance
 * por departamento lo aplica ConsultaRiesgos.
 */
class FiltrosReporteRequest extends FormRequest
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
            'departamento_id' => ['sometimes', 'integer', Rule::exists('departamentos', 'id')],
            'proceso_id' => ['sometimes', 'integer', Rule::exists('procesos', 'id')],
            'factor_id' => ['sometimes', 'integer', Rule::exists('factores_riesgo', 'id')],
            'nivel_id' => ['sometimes', 'integer', Rule::exists('niveles_riesgo', 'id')],
            'responsable' => ['sometimes', 'string', 'max:255'],
            'estado_tratamiento' => ['sometimes', Rule::enum(EstadoTratamiento::class)],
            'desde' => ['sometimes', 'date_format:Y-m-d'],
            'hasta' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'prioritarios' => ['sometimes', 'boolean'],
            'atrasados' => ['sometimes', 'boolean'],
        ];
    }
}
