<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FactorRiesgoResource;
use App\Models\FactorRiesgo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Factores de riesgo: no se borran (riesgos históricos los referencian).
 * Para retirarlos se desactivan con PUT { activo: false }.
 */
class FactorRiesgoController extends Controller
{
    /**
     * Filtro opcional ?activo=1|0 (los formularios solo ofrecen los activos).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', FactorRiesgo::class);

        $filtros = $request->validate([
            'activo' => ['sometimes', 'boolean'],
        ]);

        $factores = FactorRiesgo::query()
            ->when(array_key_exists('activo', $filtros), fn ($query) => $query->where('activo', $request->boolean('activo')))
            ->orderBy('nombre')
            ->get();

        return FactorRiesgoResource::collection($factores);
    }

    public function store(Request $request): FactorRiesgoResource
    {
        $this->authorize('create', FactorRiesgo::class);

        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:factores_riesgo,slug'],
            'activo' => ['boolean'],
        ]);

        $factor = FactorRiesgo::create($validated);

        return new FactorRiesgoResource($factor);
    }

    public function show(FactorRiesgo $factorRiesgo): FactorRiesgoResource
    {
        $this->authorize('view', $factorRiesgo);

        return new FactorRiesgoResource($factorRiesgo);
    }

    public function update(Request $request, FactorRiesgo $factorRiesgo): FactorRiesgoResource
    {
        $this->authorize('update', $factorRiesgo);

        $validated = $request->validate([
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', 'unique:factores_riesgo,slug,'.$factorRiesgo->id],
            'activo' => ['boolean'],
        ]);

        $factorRiesgo->update($validated);

        return new FactorRiesgoResource($factorRiesgo);
    }
}
