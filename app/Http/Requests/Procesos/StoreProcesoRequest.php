<?php

namespace App\Http\Requests\Procesos;

use App\Http\Requests\Concerns\GeneraSlug;
use App\Models\Departamento;
use App\Models\Proceso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LogicException;

/**
 * POST /api/departamentos/{departamento}/procesos: nueva matriz de un
 * departamento. El departamento sale de la URL, nunca del cuerpo de la
 * petición; el creador, de la sesión.
 *
 * Opcional: `edicion_inicial` {nombre, fecha_revision} crea en la misma
 * transacción la primera edición, siempre en borrador.
 */
class StoreProcesoRequest extends FormRequest
{
    use GeneraSlug;

    public function authorize(): bool
    {
        return $this->user()->can('create', [Proceso::class, $this->departamento()]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            // Único dentro del departamento: el mismo proceso existe en varias unidades.
            'slug' => [
                'required', 'string', 'max:255', self::FORMATO_SLUG,
                Rule::unique('procesos', 'slug')->where('departamento_id', $this->departamento()->id),
            ],
            'descripcion' => ['nullable', 'string'],
            'activo' => ['boolean'],

            'edicion_inicial' => ['sometimes', 'nullable', 'array'],
            'edicion_inicial.nombre' => ['required_with:edicion_inicial', 'string', 'max:255'],
            'edicion_inicial.fecha_revision' => ['required_with:edicion_inicial', 'date_format:Y-m-d'],
            // Igual que al crear una edición suelta: nace en borrador y sin origen.
            'edicion_inicial.estado' => ['prohibited'],
            'edicion_inicial.proceso_id' => ['prohibited'],
            'edicion_inicial.edicion_anterior_id' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        // El slug sale del nombre: el encargado no ve ese campo.
        return [
            'slug.unique' => 'Ya existe un proceso con ese nombre en este departamento.',
        ];
    }

    /**
     * Datos del proceso, sin la edición inicial.
     *
     * @return array<string, mixed>
     */
    public function datosProceso(): array
    {
        return Arr::except($this->validated(), 'edicion_inicial');
    }

    /**
     * @return array{nombre: string, fecha_revision: string}|null
     */
    public function edicionInicial(): ?array
    {
        /** @var array{nombre: string, fecha_revision: string}|null $edicion */
        $edicion = $this->validated('edicion_inicial');

        return $edicion;
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->departamento()->activo) {
                    $validator->errors()->add('departamento', 'No se pueden crear procesos en un departamento inactivo.');
                }
            },
        ];
    }

    public function departamento(): Departamento
    {
        $departamento = $this->route('departamento');

        if (! $departamento instanceof Departamento) {
            throw new LogicException('StoreProcesoRequest requiere el parámetro de ruta {departamento}.');
        }

        return $departamento;
    }
}
