<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Movimientos\FiltrosMovimientosRequest;
use App\Http\Resources\MovimientoResource;
use App\Models\Auditoria;
use App\Models\Departamento;
use App\Models\Matriz;
use App\Models\Proceso;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Historial de movimientos por área (solo lectura; los registros los escribe
 * App\Services\Auditor). Es una vista de la tabla auditoria limitada a los
 * registros con departamento:
 *   GET /api/movimientos            listado paginado con filtros
 *   GET /api/movimientos/opciones   valores para los filtros, dentro del alcance
 *   GET /api/movimientos/{id}       detalle con valores anteriores y nuevos
 */
class MovimientoController extends Controller
{
    public function index(FiltrosMovimientosRequest $request): AnonymousResourceCollection
    {
        $filtros = $request->validated();

        $movimientos = $this->alcance($request->user())
            ->with(['usuario', 'departamento', 'proceso', 'matriz'])
            ->when($filtros['usuario_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filtros['departamento_id'] ?? null, fn ($q, $v) => $q->where('departamento_id', $v))
            ->when($filtros['proceso_id'] ?? null, fn ($q, $v) => $q->where('proceso_id', $v))
            ->when($filtros['matriz_id'] ?? null, fn ($q, $v) => $q->where('matriz_id', $v))
            ->when($filtros['evento'] ?? null, fn ($q, $v) => $q->where('evento', $v))
            ->when($filtros['entidad'] ?? null, fn ($q, $v) => $q->where('entidad', $v))
            ->when($filtros['desde'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v.' 00:00:00'))
            ->when($filtros['hasta'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', $v.' 23:59:59'))
            ->orderByDesc('id')
            ->paginate((int) ($filtros['per_page'] ?? FiltrosMovimientosRequest::POR_PAGINA));

        return MovimientoResource::collection($movimientos);
    }

    public function show(Auditoria $movimiento): MovimientoResource
    {
        // Lo que no pertenece a un área (catálogos, usuarios, sesiones) no
        // forma parte de este historial: para todos, como si no existiera.
        abort_if($movimiento->departamento_id === null, 404);

        $this->authorize('verMovimiento', $movimiento);

        return (new MovimientoResource($movimiento->load(['usuario', 'departamento', 'proceso', 'matriz'])))->conDetalle();
    }

    /**
     * Opciones de los filtros: solo usuarios, procesos y ediciones que tienen
     * movimientos dentro del alcance del usuario, para no revelar nada ajeno.
     */
    public function opciones(Request $request): JsonResponse
    {
        $this->authorize('verMovimientos', Auditoria::class);

        /** @var User $user */
        $user = $request->user();
        $ids = fn (string $columna) => $this->alcance($user)->whereNotNull($columna)->distinct()->select($columna);

        return response()->json(['data' => [
            'usuarios' => User::whereIn('id', $ids('user_id'))->orderBy('name')->get(['id', 'name']),
            'departamentos' => Departamento::visiblesPara($user)->orderBy('nombre')->get(['id', 'nombre']),
            'procesos' => Proceso::whereIn('id', $ids('proceso_id'))->orderBy('nombre')->get(['id', 'nombre', 'departamento_id']),
            'matrices' => Matriz::withTrashed()->whereIn('id', $ids('matriz_id'))
                ->orderByDesc('fecha_revision')->orderByDesc('id')
                ->get(['id', 'nombre', 'proceso_id']),
            'eventos' => $this->alcance($user)->distinct()->orderBy('evento')->pluck('evento'),
            'entidades' => $this->alcance($user)->whereNotNull('entidad')->distinct()->orderBy('entidad')->pluck('entidad'),
        ]]);
    }

    /** @return Builder<Auditoria> */
    private function alcance(User $user): Builder
    {
        return Auditoria::query()->movimientosVisiblesPara($user);
    }
}
