<?php

namespace App\Services;

use App\Models\Matriz;
use App\Models\Riesgo;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Alta, edición y copia de riesgos. El nivel calculado siempre lo fija la
 * CalculadoraNivelRiesgo; nunca llega desde la petición.
 */
class RiesgoService
{
    public function __construct(private readonly CalculadoraNivelRiesgo $calculadora) {}

    /**
     * @param  array<string, mixed>  $datos  validados por RiesgoRequest
     * @param  Riesgo|null  $origen  riesgo equivalente de la edición anterior (importación)
     */
    public function crear(Matriz $matriz, array $datos, ?Riesgo $origen = null): Riesgo
    {
        return DB::transaction(function () use ($matriz, $datos, $origen) {
            $riesgo = new Riesgo(Arr::except($datos, 'factor_ids'));
            $riesgo->matriz_id = $matriz->id;
            $riesgo->riesgo_origen_id = $origen?->id;
            // Incluye los descartados para no reutilizar números.
            $riesgo->orden = (int) Riesgo::withTrashed()->where('matriz_id', $matriz->id)->max('orden') + 1;
            $this->fijarNivelCalculado($riesgo);
            $riesgo->save();

            $this->sincronizarFactores($riesgo, $datos['factor_ids'] ?? []);

            return $riesgo;
        });
    }

    /**
     * @param  array<string, mixed>  $datos  validados por RiesgoRequest (parciales)
     */
    public function actualizar(Riesgo $riesgo, array $datos): Riesgo
    {
        return DB::transaction(function () use ($riesgo, $datos) {
            $riesgo->fill(Arr::except($datos, 'factor_ids'));
            // En un borrador el nivel se recalcula con la matriz de calor actual.
            $this->fijarNivelCalculado($riesgo);
            $riesgo->save();

            if (array_key_exists('factor_ids', $datos)) {
                $this->sincronizarFactores($riesgo, $datos['factor_ids']);
            }

            return $riesgo;
        });
    }

    /**
     * Copia los riesgos vigentes (no descartados) de una edición a otra,
     * enlazando cada copia con su original. Se llama dentro de la
     * transacción de MatrizService::clonar.
     */
    public function copiar(Matriz $origen, Matriz $destino): void
    {
        $origen->riesgos()->with('factores')->get()->each(function (Riesgo $original) use ($destino) {
            $copia = $original->replicate(['matriz_id', 'riesgo_origen_id', 'nivel_calculado_id', 'created_at', 'updated_at', 'deleted_at']);
            $copia->matriz_id = $destino->id;
            $copia->riesgo_origen_id = $original->id;
            $this->fijarNivelCalculado($copia);
            $copia->save();

            $copia->factores()->sync($original->factores->pluck('id'));
        });
    }

    /**
     * La tabla riesgo_factor no dispara eventos de modelo: el cambio se audita aquí.
     *
     * @param  array<int, int>  $factorIds
     */
    private function sincronizarFactores(Riesgo $riesgo, array $factorIds): void
    {
        $antes = $riesgo->factores()->pluck('factores_riesgo.id')->map(fn ($id) => (int) $id)->all();
        $riesgo->factores()->sync($factorIds);

        Auditor::registrarRelacion('riesgo', $riesgo->id, 'factor_ids', $antes, array_map('intval', $factorIds));
    }

    private function fijarNivelCalculado(Riesgo $riesgo): void
    {
        $riesgo->nivel_calculado_id = $this->calculadora
            ->calcular($riesgo->probabilidad, $riesgo->impacto)
            ?->id;
    }
}
