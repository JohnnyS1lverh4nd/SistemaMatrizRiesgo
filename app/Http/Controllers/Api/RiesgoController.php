<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Riesgos\RiesgoRequest;
use App\Http\Resources\RiesgoResource;
use App\Models\Matriz;
use App\Models\Riesgo;
use App\Services\RiesgoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Riesgos de una edición de matriz (rutas shallow):
 *   GET|POST /api/matrices/{matriz}/riesgos
 *   GET|PUT|DELETE /api/riesgos/{riesgo}
 */
class RiesgoController extends Controller
{
    private const RELACIONES = ['factores', 'nivelCalculado', 'nivelAjustado', 'periodicidad', 'matriz.proceso.departamento'];

    public function __construct(private readonly RiesgoService $servicio) {}

    public function index(Matriz $matriz): AnonymousResourceCollection
    {
        $this->authorize('view', $matriz);

        return RiesgoResource::collection($matriz->riesgos()->with(self::RELACIONES)->get());
    }

    public function store(RiesgoRequest $request, Matriz $matriz): JsonResponse
    {
        $riesgo = $this->servicio->crear($matriz, $request->validated());

        return (new RiesgoResource($riesgo->load(self::RELACIONES)))->response()->setStatusCode(201);
    }

    public function show(Riesgo $riesgo): RiesgoResource
    {
        $this->authorize('view', $riesgo);

        return new RiesgoResource($riesgo->load(self::RELACIONES));
    }

    public function update(RiesgoRequest $request, Riesgo $riesgo): RiesgoResource
    {
        $riesgo = $this->servicio->actualizar($riesgo, $request->validated());

        return new RiesgoResource($riesgo->load(self::RELACIONES));
    }

    /** Eliminación lógica (solo en borradores). */
    public function destroy(Riesgo $riesgo): Response
    {
        $this->authorize('delete', $riesgo);

        $riesgo->delete();

        return response()->noContent();
    }
}
