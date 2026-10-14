<?php

namespace App\Services;

use App\Enums\EstadoMatriz;
use App\Models\Matriz;
use App\Models\Proceso;
use Illuminate\Support\Facades\DB;

/**
 * Ciclo de vida de las ediciones de matriz. Cada operación que toca varias
 * filas va en una transacción: o se aplica completa o no se aplica.
 */
class MatrizService
{
    /**
     * Publica un borrador. La vigente anterior del mismo proceso se archiva
     * en la misma transacción: nunca hay dos vigentes ni cero por un fallo
     * intermedio.
     */
    public function publicar(Matriz $matriz): Matriz
    {
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

            // Fase 4: aquí se copiarán los riesgos del origen, enlazando cada
            // copia con su riesgo original para poder comparar ediciones (RF-5).

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
