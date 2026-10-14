<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PeriodicidadResource;
use App\Models\Periodicidad;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

/**
 * Periodicidades: no se borran (riesgos históricos las referencian).
 * Para retirarlas se desactivan con PUT { activo: false }.
 */
class PeriodicidadController extends Controller
{
    /**
     * Filtro opcional ?activo=1|0 (los formularios solo ofrecen las activas).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Periodicidad::class);

        $filtros = $request->validate([
            'activo' => ['sometimes', 'boolean'],
        ]);

        $periodicidades = Periodicidad::query()
            ->when(array_key_exists('activo', $filtros), fn ($query) => $query->where('activo', $request->boolean('activo')))
            ->orderBy('nombre')
            ->get();

        return PeriodicidadResource::collection($periodicidades);
    }

    public function store(Request $request): PeriodicidadResource
    {
        $this->authorize('create', Periodicidad::class);

        // Igual que departamentos y procesos: sin slug, se genera del nombre.
        if (! $request->filled('slug') && is_string($request->input('nombre'))) {
            $request->merge(['slug' => Str::slug($request->input('nombre'))]);
        }

        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:periodicidades,slug'],
            // Cada cuántos días toca seguimiento; null = sin plazo ("Cuando ocurra").
            'frecuencia_dias' => ['nullable', 'integer', 'min:1', 'max:3650'],
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
            // Cada cuántos días toca seguimiento; null = sin plazo ("Cuando ocurra").
            'frecuencia_dias' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'activo' => ['boolean'],
        ]);

        $periodicidad->update($validated);

        return new PeriodicidadResource($periodicidad);
    }
}
