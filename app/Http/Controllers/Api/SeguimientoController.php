<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seguimientos\AnularSeguimientoRequest;
use App\Http\Requests\Seguimientos\StoreSeguimientoRequest;
use App\Http\Resources\SeguimientoResource;
use App\Models\Riesgo;
use App\Models\Seguimiento;
use App\Services\SeguimientoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Histórico de seguimiento de un riesgo (no se edita ni se borra):
 *   GET|POST /api/riesgos/{riesgo}/seguimientos
 *   GET /api/seguimientos/{seguimiento}
 *   POST /api/seguimientos/{seguimiento}/anular
 */
class SeguimientoController extends Controller
{
    private const RELACIONES = ['registradoPor', 'anuladoPor', 'evidencias', 'riesgo.matriz.proceso.departamento'];

    public function __construct(private readonly SeguimientoService $servicio) {}

    /** Más reciente primero. */
    public function index(Riesgo $riesgo): AnonymousResourceCollection
    {
        $this->authorize('view', $riesgo);

        $seguimientos = $riesgo->seguimientos()
            ->with(self::RELACIONES)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        return SeguimientoResource::collection($seguimientos);
    }

    public function store(StoreSeguimientoRequest $request, Riesgo $riesgo): JsonResponse
    {
        /** @var array{fecha: string, descripcion: string, estado_tratamiento?: string|null} $datos */
        $datos = $request->safe()->except('archivos');

        $seguimiento = $this->servicio->registrar($riesgo, $request->user(), $datos, $request->file('archivos', []));

        return (new SeguimientoResource($seguimiento->load(self::RELACIONES)))->response()->setStatusCode(201);
    }

    public function show(Seguimiento $seguimiento): SeguimientoResource
    {
        $this->authorize('view', $seguimiento->riesgo);

        return new SeguimientoResource($seguimiento->load(self::RELACIONES));
    }

    public function anular(AnularSeguimientoRequest $request, Seguimiento $seguimiento): SeguimientoResource
    {
        $seguimiento = $this->servicio->anular($seguimiento, $request->user(), $request->string('motivo')->toString());

        return new SeguimientoResource($seguimiento->load(self::RELACIONES));
    }
}
