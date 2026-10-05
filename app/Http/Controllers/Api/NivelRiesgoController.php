<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NivelRiesgoResource;
use App\Models\NivelRiesgo;
use Illuminate\Http\Request;

class NivelRiesgoController extends Controller
{
    public function index(): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $this->authorize('viewAny', NivelRiesgo::class);

        return NivelRiesgoResource::collection(NivelRiesgo::orderBy('orden')->get());
    }

    public function store(Request $request): NivelRiesgoResource
    {
        $this->authorize('create', NivelRiesgo::class);

        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:niveles_riesgo,slug'],
            'color' => ['required', 'string', 'max:7'],
            'orden' => ['required', 'integer', 'unique:niveles_riesgo,orden'],
        ]);

        $nivel = NivelRiesgo::create($validated);

        return new NivelRiesgoResource($nivel);
    }

    public function show(NivelRiesgo $nivelRiesgo): NivelRiesgoResource
    {
        $this->authorize('view', $nivelRiesgo);

        return new NivelRiesgoResource($nivelRiesgo);
    }

    public function update(Request $request, NivelRiesgo $nivelRiesgo): NivelRiesgoResource
    {
        $this->authorize('update', $nivelRiesgo);

        $validated = $request->validate([
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', 'unique:niveles_riesgo,slug,' . $nivelRiesgo->id],
            'color' => ['sometimes', 'required', 'string', 'max:7'],
            'orden' => ['sometimes', 'required', 'integer', 'unique:niveles_riesgo,orden,' . $nivelRiesgo->id],
        ]);

        $nivelRiesgo->update($validated);

        return new NivelRiesgoResource($nivelRiesgo);
    }

    public function destroy(NivelRiesgo $nivelRiesgo): \Illuminate\Http\Response
    {
        $this->authorize('delete', $nivelRiesgo);

        $nivelRiesgo->delete();

        return response()->noContent();
    }
}