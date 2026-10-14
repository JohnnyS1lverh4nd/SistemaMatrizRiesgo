<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procesos\StoreProcesoRequest;
use App\Http\Requests\Procesos\UpdateProcesoRequest;
use App\Http\Resources\ProcesoResource;
use App\Models\Departamento;
use App\Models\Proceso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Procesos de un departamento (rutas shallow):
 *   GET|POST  /api/departamentos/{departamento}/procesos
 *   GET|PUT   /api/procesos/{proceso}
 * Sin destroy: un proceso se retira con { activo: false }.
 */
class ProcesoController extends Controller
{
    /**
     * Filtro opcional ?activo=1|0.
     */
    public function index(Request $request, Departamento $departamento): AnonymousResourceCollection
    {
        // Quien puede ver el departamento puede ver sus procesos.
        $this->authorize('view', $departamento);

        $filtros = $request->validate([
            'activo' => ['sometimes', 'boolean'],
        ]);

        $procesos = $departamento->procesos()
            ->when(array_key_exists('activo', $filtros), fn ($query) => $query->where('activo', $request->boolean('activo')))
            ->orderBy('nombre')
            ->get();

        return ProcesoResource::collection($procesos);
    }

    public function store(StoreProcesoRequest $request, Departamento $departamento): JsonResponse
    {
        $proceso = $departamento->procesos()->create($request->validated());

        return (new ProcesoResource($proceso))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Proceso $proceso): ProcesoResource
    {
        $this->authorize('view', $proceso);

        return new ProcesoResource($proceso);
    }

    public function update(UpdateProcesoRequest $request, Proceso $proceso): ProcesoResource
    {
        $proceso->update($request->validated());

        return new ProcesoResource($proceso);
    }
}
