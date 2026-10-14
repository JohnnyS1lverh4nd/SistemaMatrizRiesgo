<?php

namespace App\Http\Requests\Matrices;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Reglas comunes de alta, clonado y edición de una edición de matriz.
 */
trait MatrizRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function reglasEdicion(int $procesoId, ?int $ignorarId = null): array
    {
        return [
            // Único por proceso entre las ediciones no descartadas.
            'nombre' => ['required', 'string', 'max:255', $this->nombreUnico($procesoId, $ignorarId)],
            'fecha_revision' => ['required', 'date_format:Y-m-d'],
            // El estado solo cambia con publicar/archivar.
            'estado' => ['prohibited'],
            'proceso_id' => ['prohibited'],
            'edicion_anterior_id' => ['prohibited'],
        ];
    }

    private function nombreUnico(int $procesoId, ?int $ignorarId): Unique
    {
        $regla = Rule::unique('matrices', 'nombre')
            ->where('proceso_id', $procesoId)
            ->whereNull('deleted_at');

        return $ignorarId ? $regla->ignore($ignorarId) : $regla;
    }
}
