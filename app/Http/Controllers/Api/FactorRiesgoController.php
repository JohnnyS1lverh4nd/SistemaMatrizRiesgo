<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FactorRiesgoResource;
use App\Models\FactorRiesgo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class FactorRiesgoController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', FactorRiesgo::class);

        return FactorRiesgoResource::collection(FactorRiesgo::all());
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

    public function destroy(FactorRiesgo $factorRiesgo): Response
    {
        $this->authorize('delete', $factorRiesgo);

        $factorRiesgo->delete();

        return response()->noContent();
    }
}
