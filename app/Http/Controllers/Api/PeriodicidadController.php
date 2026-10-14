<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PeriodicidadResource;
use App\Models\Periodicidad;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PeriodicidadController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Periodicidad::class);

        return PeriodicidadResource::collection(Periodicidad::all());
    }

    public function store(Request $request): PeriodicidadResource
    {
        $this->authorize('create', Periodicidad::class);

        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:periodicidades,slug'],
            'activo' => ['boolean'],
        ]);

        $periodicidad = Periodicidad::create($validated);

        return new PeriodicidadResource($periodicidad);
    }

    public function show(Periodicidad $periodicidad): PeriodicidadResource
    {
        $this->authorize('view', $periodicidad);

        return new PeriodicidadResource($periodicidad);
    }

    public function update(Request $request, Periodicidad $periodicidad): PeriodicidadResource
    {
        $this->authorize('update', $periodicidad);

        $validated = $request->validate([
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', 'unique:periodicidades,slug,'.$periodicidad->id],
            'activo' => ['boolean'],
        ]);

        $periodicidad->update($validated);

        return new PeriodicidadResource($periodicidad);
    }

    public function destroy(Periodicidad $periodicidad): Response
    {
        $this->authorize('delete', $periodicidad);

        $periodicidad->delete();

        return response()->noContent();
    }
}
