<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Departamentos\StoreDepartamentoRequest;
use App\Http\Requests\Departamentos\UpdateDepartamentoRequest;
use App\Http\Resources\DepartamentoResource;
use App\Models\Departamento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DepartamentoController extends Controller
{
    /**
     * Catálogo pequeño (decenas de registros): se devuelve completo, sin paginar.
     * Filtro opcional: ?activo=1|0.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Departamento::class);

        $filtros = $request->validate([
            'activo' => ['sometimes', 'boolean'],
        ]);

        $departamentos = Departamento::visiblesPara($request->user())
            ->when(array_key_exists('activo', $filtros), fn ($query) => $query->where('activo', $request->boolean('activo')))
            ->orderBy('nombre')
            ->get();

        return DepartamentoResource::collection($departamentos);
    }

    public function store(StoreDepartamentoRequest $request): JsonResponse
    {
        $departamento = Departamento::create($request->validated());

        return (new DepartamentoResource($departamento))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Departamento $departamento): DepartamentoResource
    {
        $this->authorize('view', $departamento);

        return new DepartamentoResource($departamento);
    }

    /**
     * Sin destroy: un departamento se retira con { activo: false } para no
     * perder sus matrices, procesos ni usuarios asignados.
     */
    public function update(UpdateDepartamentoRequest $request, Departamento $departamento): DepartamentoResource
    {
        $departamento->update($request->validated());

        return new DepartamentoResource($departamento);
    }
}
