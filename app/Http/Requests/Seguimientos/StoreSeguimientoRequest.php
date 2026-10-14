<?php

namespace App\Http\Requests\Seguimientos;

use App\Enums\EstadoTratamiento;
use App\Models\Riesgo;
use App\Models\Seguimiento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use LogicException;

/**
 * POST /api/riesgos/{riesgo}/seguimientos (multipart si lleva archivos).
 */
class StoreSeguimientoRequest extends FormRequest
{
    /** Evidencias admitidas (decisión oct. 2026): PDF, imágenes y Excel. */
    public const EXTENSIONES = ['pdf', 'jpg', 'jpeg', 'png', 'xlsx', 'xls'];

    public const MAX_KB = 10 * 1024;

    public const MAX_ARCHIVOS = 5;

    public function authorize(): bool
    {
        return $this->user()->can('create', [Seguimiento::class, $this->riesgo()]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'descripcion' => ['required', 'string', 'max:10000'],
            'estado_tratamiento' => ['nullable', Rule::enum(EstadoTratamiento::class)],
            'archivos' => ['array', 'max:'.self::MAX_ARCHIVOS],
            // types() comprueba el contenido real del archivo, no solo su extensión.
            'archivos.*' => [File::types(self::EXTENSIONES)->max(self::MAX_KB)],
            // Los fija el sistema: usuario de la sesión y riesgo de la URL.
            'user_id' => ['prohibited'],
            'riesgo_id' => ['prohibited'],
        ];
    }

    public function riesgo(): Riesgo
    {
        $riesgo = $this->route('riesgo');

        if (! $riesgo instanceof Riesgo) {
            throw new LogicException('StoreSeguimientoRequest requiere el parámetro de ruta {riesgo}.');
        }

        return $riesgo;
    }
}
