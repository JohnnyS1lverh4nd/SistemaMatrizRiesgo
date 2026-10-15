<?php

namespace App\Http\Requests\Importacion;

use App\Models\Matriz;
use App\Models\Proceso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;
use LogicException;

/**
 * POST /api/procesos/{proceso}/importacion[/vista-previa] (multipart).
 */
class ImportarMatricesRequest extends FormRequest
{
    public const MAX_KB = 5 * 1024;

    public function authorize(): bool
    {
        return $this->user()->can('importar', [Matriz::class, $this->proceso()]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // types() comprueba el contenido real del archivo, no solo su extensión.
            'archivo' => ['required', File::types(['xlsx'])->max(self::MAX_KB)],
        ];
    }

    public function proceso(): Proceso
    {
        $proceso = $this->route('proceso');

        if (! $proceso instanceof Proceso) {
            throw new LogicException('ImportarMatricesRequest requiere el parámetro de ruta {proceso}.');
        }

        return $proceso;
    }
}
