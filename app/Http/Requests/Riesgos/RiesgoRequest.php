<?php

namespace App\Http\Requests\Riesgos;

use App\Enums\EstadoTratamiento;
use App\Models\Matriz;
use App\Models\Riesgo;
use App\Services\CalculadoraNivelRiesgo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LogicException;

/**
 * Alta (POST /api/matrices/{matriz}/riesgos) y edición parcial
 * (PUT /api/riesgos/{riesgo}) de un riesgo.
 */
class RiesgoRequest extends FormRequest
{
    private const TEXTO_LARGO = ['nullable', 'string', 'max:10000'];

    public function authorize(): bool
    {
        $riesgo = $this->riesgo();

        return $riesgo
            ? $this->user()->can('update', $riesgo)
            : $this->user()->can('create', [Riesgo::class, $this->matriz()]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $escala = 'between:'.CalculadoraNivelRiesgo::MINIMO.','.CalculadoraNivelRiesgo::MAXIMO;

        $reglas = [
            'actividad' => ['required', 'string', 'max:10000'],
            'descripcion' => ['required', 'string', 'max:10000'],
            'causas' => self::TEXTO_LARGO,
            'control_actual' => self::TEXTO_LARGO,
            // Se evalúan juntas; ambas vacías = riesgo aún sin evaluar.
            'probabilidad' => ['nullable', 'integer', $escala, 'required_with:impacto'],
            'impacto' => ['nullable', 'integer', $escala, 'required_with:probabilidad'],
            'factor_ids' => ['array'],
            'factor_ids.*' => ['integer', 'distinct', Rule::exists('factores_riesgo', 'id')->where('activo', true)],
            'sintoma' => self::TEXTO_LARGO,
            'tratamiento' => self::TEXTO_LARGO,
            'responsable' => self::TEXTO_LARGO,
            'periodicidad_id' => ['nullable', 'integer', Rule::exists('periodicidades', 'id')->where('activo', true)],
            'estado_tratamiento' => [Rule::enum(EstadoTratamiento::class)],
            'nivel_ajustado_id' => ['nullable', 'integer', Rule::exists('niveles_riesgo', 'id')],
            'justificacion_ajuste' => ['nullable', 'string', 'max:10000'],
            // Los fija el sistema.
            'nivel_calculado_id' => ['prohibited'],
            'matriz_id' => ['prohibited'],
            'riesgo_origen_id' => ['prohibited'],
            'orden' => ['prohibited'],
        ];

        if ($this->riesgo() === null) {
            return $reglas;
        }

        // Edición parcial: solo se validan los campos enviados.
        return collect($reglas)
            ->map(fn (array $r) => in_array('prohibited', $r, true) ? $r : ['sometimes', ...$r])
            ->all();
    }

    /**
     * Reglas del ajuste manual (RF-3) sobre los valores finales del riesgo
     * (lo enviado combinado con lo ya guardado).
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $ajuste = $this->valorFinal('nivel_ajustado_id');
                $justificacion = trim((string) $this->valorFinal('justificacion_ajuste'));

                if ($ajuste === null) {
                    if ($justificacion !== '') {
                        $validator->errors()->add('justificacion_ajuste', 'La justificación solo aplica cuando se ajusta el nivel.');
                    }

                    return;
                }

                if ($justificacion === '') {
                    $validator->errors()->add('justificacion_ajuste', 'Justifique el ajuste manual del nivel de riesgo.');
                }

                $probabilidad = $this->valorFinal('probabilidad');
                $impacto = $this->valorFinal('impacto');

                if ($probabilidad === null || $impacto === null) {
                    $validator->errors()->add('nivel_ajustado_id', 'Evalúe la probabilidad y el impacto antes de ajustar el nivel.');

                    return;
                }

                $calculado = app(CalculadoraNivelRiesgo::class)->calcular((int) $probabilidad, (int) $impacto);

                if ($calculado && (int) $ajuste === $calculado->id) {
                    $validator->errors()->add('nivel_ajustado_id', 'El nivel ajustado coincide con el calculado; deje el ajuste vacío.');
                }
            },
        ];
    }

    private function valorFinal(string $campo): mixed
    {
        if ($this->exists($campo)) {
            return $this->input($campo);
        }

        return $this->riesgo()?->getAttribute($campo);
    }

    public function riesgo(): ?Riesgo
    {
        $riesgo = $this->route('riesgo');

        return $riesgo instanceof Riesgo ? $riesgo : null;
    }

    public function matriz(): Matriz
    {
        $riesgo = $this->riesgo();
        $matriz = $riesgo !== null ? $riesgo->matriz : $this->route('matriz');

        if (! $matriz instanceof Matriz) {
            throw new LogicException('RiesgoRequest requiere el parámetro de ruta {matriz} o {riesgo}.');
        }

        return $matriz;
    }
}
