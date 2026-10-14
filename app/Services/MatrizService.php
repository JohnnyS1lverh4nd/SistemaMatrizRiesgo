<?php

namespace App\Services;

use App\Enums\EstadoMatriz;
use App\Models\Matriz;
use App\Models\Proceso;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ciclo de vida de las ediciones de matriz. Cada operación que toca varias
 * filas va en una transacción: o se aplica completa o no se aplica.
 */
class MatrizService
{
    public function __construct(private readonly RiesgoService $riesgos) {}

    /**
     * Publica un borrador. La vigente anterior del mismo proceso se archiva
     * en la misma transacción: nunca hay dos vigentes ni cero por un fallo
     * intermedio.
     *
     * @throws ValidationException si hay riesgos sin evaluar
     */
    public function publicar(Matriz $matriz): Matriz
    {
        $sinEvaluar = $matriz->riesgos()->where(fn ($q) => $q->whereNull('probabilidad')->orWhereNull('impacto'))->count();

        if ($sinEvaluar > 0) {
            throw ValidationException::withMessages([
                'riesgos' => $sinEvaluar === 1
                    ? 'Hay 1 riesgo sin evaluar (probabilidad e impacto). Evalúelo antes de publicar.'
                    : "Hay {$sinEvaluar} riesgos sin evaluar (probabilidad e impacto). Evalúelos antes de publicar.",
            ]);
        }

        return DB::transaction(function () use ($matriz) {
            // Bloquea el proceso para serializar publicaciones simultáneas.
            Proceso::whereKey($matriz->proceso_id)->lockForUpdate()->first();

            Matriz::where('proceso_id', $matriz->proceso_id)
                ->where('estado', EstadoMatriz::Vigente)
                ->whereKeyNot($matriz->id)
                ->get()
                ->each(fn (Matriz $vigente) => $this->marcarArchivada($vigente));

            $matriz->forceFill([
                'estado' => EstadoMatriz::Vigente,
                'publicada_at' => now(),
            ])->save();

            return $matriz;
        });
    }

    public function archivar(Matriz $matriz): Matriz
    {
        return DB::transaction(fn () => $this->marcarArchivada($matriz));
    }

    /**
     * Crea un borrador nuevo a partir de otra edición, sin modificarla.
     *
     * @param  array{nombre: string, fecha_revision: string}  $datos
     */
    public function clonar(Matriz $origen, array $datos): Matriz
    {
        return DB::transaction(function () use ($origen, $datos) {
            $copia = Matriz::create([
                'proceso_id' => $origen->proceso_id,
                'nombre' => $datos['nombre'],
                'fecha_revision' => $datos['fecha_revision'],
                'edicion_anterior_id' => $origen->id,
            ]);

            // Cada riesgo copiado queda enlazado con su original (comparación, RF-5).
            $this->riesgos->copiar($origen, $copia);

            return $copia;
        });
    }

    private function marcarArchivada(Matriz $matriz): Matriz
    {
        $matriz->forceFill([
            'estado' => EstadoMatriz::Archivada,
            'archivada_at' => now(),
        ])->save();

        return $matriz;
    }
}
