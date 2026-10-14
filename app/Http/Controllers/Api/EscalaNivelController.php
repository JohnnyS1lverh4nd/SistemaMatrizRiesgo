<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EscalaNivelResource;
use App\Models\EscalaNivel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Escalas 1–4 de Probabilidad e Impacto: estructura fija (la matriz de calor
 * tiene siempre 4×4 celdas). Solo se editan nombre y descripción.
 */
class EscalaNivelController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', EscalaNivel::class);

        return EscalaNivelResource::collection(
            EscalaNivel::orderBy('tipo')->orderBy('valor')->get()
        );
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
}
