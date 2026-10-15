<?php

namespace App\Services\Reportes;

use App\Enums\EstadoMatriz;
use App\Enums\EstadoTratamiento;
use App\Models\Departamento;
use App\Models\NivelRiesgo;
use App\Models\Proceso;
use App\Models\Riesgo;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Consulta común del dashboard, el reporte de riesgos y la exportación:
 * así los tres cuentan exactamente lo mismo.
 *
 * - Alcance: solo departamentos que el usuario puede ver (visiblesPara).
 * - Sin rango de fechas: solo ediciones vigentes (situación actual).
 * - Con rango: ediciones vigentes y archivadas cuya fecha de revisión cae en
 *   el rango. Los borradores nunca cuentan.
 */
class ConsultaRiesgos
{
    /** Nivel que rige: el ajustado si existe; si no, el calculado. */
    public const NIVEL_EFECTIVO = 'COALESCE(riesgos.nivel_ajustado_id, riesgos.nivel_calculado_id)';

    /**
     * @param  array{departamento_id?: int|string, proceso_id?: int|string, factor_id?: int|string, nivel_id?: int|string,
     *               responsable?: string, estado_tratamiento?: string, desde?: string, hasta?: string, prioritarios?: bool|string|int}  $filtros
     * @return Builder<Riesgo>
     *
     * @throws AuthorizationException si se filtra por un departamento o proceso fuera de su alcance
     */
    public function para(User $user, array $filtros = []): Builder
    {
        $this->autorizarFiltros($user, $filtros);

        $conRango = isset($filtros['desde']) || isset($filtros['hasta']);
        $estados = $conRango ? [EstadoMatriz::Vigente, EstadoMatriz::Archivada] : [EstadoMatriz::Vigente];

        return Riesgo::query()
            ->whereHas('matriz', function (Builder $matriz) use ($user, $filtros, $estados) {
                $matriz->whereIn('estado', $estados)
                    ->when($filtros['desde'] ?? null, fn ($q, $v) => $q->whereDate('fecha_revision', '>=', $v))
                    ->when($filtros['hasta'] ?? null, fn ($q, $v) => $q->whereDate('fecha_revision', '<=', $v))
                    ->when($filtros['proceso_id'] ?? null, fn ($q, $v) => $q->where('proceso_id', $v))
                    // Procesos y departamentos retirados (activo = false) no cuentan;
                    // sus ediciones siguen consultables por su URL.
                    ->whereHas('proceso', fn (Builder $proceso) => $proceso
                        ->where('activo', true)
                        ->whereIn('departamento_id', Departamento::visiblesPara($user)->where('departamentos.activo', true)->select('departamentos.id'))
                        ->when($filtros['departamento_id'] ?? null, fn ($q, $v) => $q->where('departamento_id', $v)));
            })
            ->when($filtros['factor_id'] ?? null, fn ($q, $v) => $q->whereHas('factores', fn ($f) => $f->whereKey($v)))
            // (int): COALESCE no tiene afinidad de tipo; en SQLite 4 = '4' es falso.
            ->when($filtros['nivel_id'] ?? null, fn ($q, $v) => $q->whereRaw(self::NIVEL_EFECTIVO.' = ?', [(int) $v]))
            ->when($filtros['responsable'] ?? null, fn ($q, $v) => $q->whereRaw('LOWER(responsable) LIKE ?', ['%'.mb_strtolower($v).'%']))
            ->when($filtros['estado_tratamiento'] ?? null, fn ($q, $v) => $q->where('estado_tratamiento', $v))
            ->when(filter_var($filtros['prioritarios'] ?? false, FILTER_VALIDATE_BOOLEAN), fn ($q) => $this->soloPrioritarios($q));
    }

    /**
     * "Riesgos críticos/altos pendientes de tratamiento" (RF-6).
     *
     * @param  Builder<Riesgo>  $query
     */
    private function soloPrioritarios(Builder $query): void
    {
        $niveles = NivelRiesgo::whereIn('slug', ['alto', 'critico'])->pluck('id')->all();

        $query->whereIn(DB::raw(self::NIVEL_EFECTIVO), $niveles)
            ->where('estado_tratamiento', '!=', EstadoTratamiento::Cumplido->value);
    }

    /**
     * Filtrar por algo fuera del alcance es un 403, no un resultado vacío:
     * coherente con el resto de la API (evita sondear qué existe).
     *
     * @param  array<string, mixed>  $filtros
     */
    private function autorizarFiltros(User $user, array $filtros): void
    {
        if (isset($filtros['departamento_id'])) {
            $departamento = Departamento::findOrFail($filtros['departamento_id']);
            if (! $user->can('view', $departamento)) {
                throw new AuthorizationException;
            }
        }

        if (isset($filtros['proceso_id'])) {
            $proceso = Proceso::findOrFail($filtros['proceso_id']);
            if (! $user->can('view', $proceso)) {
                throw new AuthorizationException;
            }
        }
    }
}
