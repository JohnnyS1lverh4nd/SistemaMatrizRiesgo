<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DepartamentoResource;
use App\Models\Departamento;
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

    public function show(Departamento $departamento): DepartamentoResource
    {
        $this->authorize('view', $departamento);

        return new DepartamentoResource($departamento);
    }
}
