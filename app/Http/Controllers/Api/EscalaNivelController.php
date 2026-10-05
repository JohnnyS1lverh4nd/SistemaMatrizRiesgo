<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EscalaNivelResource;
use App\Models\EscalaNivel;
use Illuminate\Http\Request;

class EscalaNivelController extends Controller
{
    public function index(): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $this->authorize('viewAny', EscalaNivel::class);

        return EscalaNivelResource::collection(
            EscalaNivel::orderBy('tipo')->orderBy('valor')->get()
        );
    }

    public function store(Request $request): EscalaNivelResource
    {
        $this->authorize('create', EscalaNivel::class);

        $validated = $request->validate([
            'tipo' => ['required', 'in:probabilidad,impacto'],
            'valor' => ['required', 'integer', 'min:1'],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
        ]);

        $escala = EscalaNivel::create($validated);

        return new EscalaNivelResource($escala);
    }

    public function show(EscalaNivel $escalaNivel): EscalaNivelResource
    {
        $this->authorize('view', $escalaNivel);

        return new EscalaNivelResource($escalaNivel);
    }

    public function update(Request $request, EscalaNivel $escalaNivel): EscalaNivelResource
    {
        $this->authorize('update', $escalaNivel);

        $validated = $request->validate([
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
        ]);

        $escalaNivel->update($validated);

        return new EscalaNivelResource($escalaNivel);
    }

    public function destroy(EscalaNivel $escalaNivel): \Illuminate\Http\Response
    {
        $this->authorize('delete', $escalaNivel);

        $escalaNivel->delete();

        return response()->noContent();
    }
}