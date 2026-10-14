<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NivelRiesgoResource;
use App\Models\NivelRiesgo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Los cuatro niveles (Bajo, Moderado, Alto, Crítico) son estructura fija de
 * la matriz de calor: no se crean ni se borran. Solo se edita su presentación.
 */
class NivelRiesgoController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', NivelRiesgo::class);

        return NivelRiesgoResource::collection(NivelRiesgo::orderBy('orden')->get());
    }

    public function show(NivelRiesgo $nivelRiesgo): NivelRiesgoResource
    {
        $this->authorize('view', $nivelRiesgo);

        return new NivelRiesgoResource($nivelRiesgo);
    }

    public function update(Request $request, NivelRiesgo $nivelRiesgo): NivelRiesgoResource
    {
        $this->authorize('update', $nivelRiesgo);

        // slug y orden no se aceptan: identifican el nivel y su gravedad.
        $validated = $request->validate([
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'color' => ['sometimes', 'required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $nivelRiesgo->update($validated);

        return new NivelRiesgoResource($nivelRiesgo);
    }
}
