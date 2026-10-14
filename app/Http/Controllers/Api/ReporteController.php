<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reportes\FiltrosReporteRequest;
use App\Http\Resources\ReporteRiesgoResource;
use App\Models\Matriz;
use App\Models\Riesgo;
use App\Services\Reportes\ComparadorEdiciones;
use App\Services\Reportes\ConsultaRiesgos;
use App\Services\Reportes\Dashboard;
use App\Services\Reportes\EstadoSeguimiento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard y reportes (RF-5, RF-6). Todo se calcula dentro del alcance del
 * usuario: nadie ve datos de departamentos que no puede consultar.
 *   GET /api/dashboard
 *   GET /api/reportes/riesgos
 *   GET /api/matrices/{matriz}/comparar/{otra}
 */
class ReporteController extends Controller
{
    private const POR_PAGINA = 50;

    public function __construct(
        private readonly ConsultaRiesgos $consulta,
        private readonly Dashboard $dashboard,
        private readonly ComparadorEdiciones $comparador,
    ) {}

    public function dashboard(FiltrosReporteRequest $request): JsonResponse
    {
        /** @var array{departamento_id?: int|string} $filtros */
        $filtros = $request->safe()->only(['departamento_id']);

        return response()->json(['data' => $this->dashboard->para($request->user(), $filtros)]);
    }

    /**
     * Más graves primero; luego departamento, proceso y orden en la matriz.
     */
    public function riesgos(FiltrosReporteRequest $request): AnonymousResourceCollection
    {
        $filtros = $request->validated();
        $query = $this->consulta->para($request->user(), $filtros);

        if ($request->boolean('atrasados')) {
            $query->whereKey($this->idsAtrasados(EstadoSeguimiento::preparar(clone $query)));
        }

        // select() va antes de preparar(): si no, borraría el withMax del último seguimiento.
        $query->leftJoin('niveles_riesgo as nivel_efectivo', 'nivel_efectivo.id', '=', DB::raw(ConsultaRiesgos::NIVEL_EFECTIVO))
            ->select('riesgos.*');

        $riesgos = EstadoSeguimiento::preparar($query)
            ->with(['factores', 'nivelCalculado', 'nivelAjustado', 'matriz.proceso.departamento'])
            ->orderByDesc('nivel_efectivo.orden')
            ->orderBy('riesgos.matriz_id')
            ->orderBy('riesgos.orden')
            ->paginate(self::POR_PAGINA);

        return ReporteRiesgoResource::collection($riesgos);
    }

    public function comparar(Matriz $matriz, Matriz $otra): JsonResponse
    {
        $this->authorize('view', $matriz);
        $this->authorize('view', $otra);

        return response()->json(['data' => $this->comparador->comparar($matriz, $otra)]);
    }

    /**
     * El atraso se calcula en PHP (ver EstadoSeguimiento); se filtra después.
     *
     * @param  Builder<Riesgo>  $query
     * @return array<int, int>
     */
    private function idsAtrasados(Builder $query): array
    {
        return $query->get()
            ->filter(fn (Riesgo $riesgo) => EstadoSeguimiento::de($riesgo)['atrasado'])
            ->map(fn (Riesgo $riesgo) => $riesgo->id)
            ->values()
            ->all();
    }
}
