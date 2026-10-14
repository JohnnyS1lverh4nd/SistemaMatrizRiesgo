<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoMatriz;
use App\Http\Controllers\Controller;
use App\Http\Requests\Matrices\ClonarMatrizRequest;
use App\Http\Requests\Matrices\StoreMatrizRequest;
use App\Http\Requests\Matrices\UpdateMatrizRequest;
use App\Http\Resources\MatrizResource;
use App\Models\Matriz;
use App\Models\Proceso;
use App\Services\MatrizService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Ediciones de la matriz de riesgos de un proceso.
 *   GET|POST /api/procesos/{proceso}/matrices
 *   GET|PUT|DELETE /api/matrices/{matriz}
 *   POST /api/matrices/{matriz}/publicar | archivar | clonar
 */
class MatrizController extends Controller
{
    public function __construct(private readonly MatrizService $servicio) {}

    /**
     * Filtro opcional ?estado=borrador|vigente|archivada. Más recientes primero.
     */
    public function index(Request $request, Proceso $proceso): AnonymousResourceCollection
    {
        $this->authorize('view', $proceso);

        $filtros = $request->validate([
            'estado' => ['sometimes', Rule::enum(EstadoMatriz::class)],
        ]);

        $matrices = $proceso->matrices()
            ->with('proceso.departamento')
            ->when(isset($filtros['estado']), fn ($query) => $query->where('estado', $filtros['estado']))
            ->orderByDesc('fecha_revision')
            ->orderByDesc('id')
            ->get();

        return MatrizResource::collection($matrices);
    }

    public function store(StoreMatrizRequest $request, Proceso $proceso): JsonResponse
    {
        $matriz = $proceso->matrices()->create($request->validated());

        return (new MatrizResource($matriz))->response()->setStatusCode(201);
    }

    public function show(Matriz $matriz): MatrizResource
    {
        $this->authorize('view', $matriz);

        return new MatrizResource($matriz);
    }

    public function update(UpdateMatrizRequest $request, Matriz $matriz): MatrizResource
    {
        $matriz->update($request->validated());

        return new MatrizResource($matriz);
    }

    /** Descarta un borrador (eliminación lógica). */
    public function destroy(Matriz $matriz): Response
    {
        $this->authorize('delete', $matriz);

        $matriz->delete();

        return response()->noContent();
    }

    public function publicar(Matriz $matriz): MatrizResource
    {
        $this->authorize('publicar', $matriz);

        return new MatrizResource($this->servicio->publicar($matriz));
    }

    public function archivar(Matriz $matriz): MatrizResource
    {
        $this->authorize('archivar', $matriz);

        return new MatrizResource($this->servicio->archivar($matriz));
    }

    public function clonar(ClonarMatrizRequest $request, Matriz $matriz): JsonResponse
    {
        /** @var array{nombre: string, fecha_revision: string} $datos */
        $datos = $request->validated();

        return (new MatrizResource($this->servicio->clonar($matriz, $datos)))
            ->response()
            ->setStatusCode(201);
    }
}
