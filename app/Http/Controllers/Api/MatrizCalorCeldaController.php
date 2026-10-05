<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MatrizCalorCeldaResource;
use App\Models\MatrizCalorCelda;
use Illuminate\Http\Request;

class MatrizCalorCeldaController extends Controller
{
    public function index(): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $this->authorize('viewAny', MatrizCalorCelda::class);

        $celdas = MatrizCalorCelda::with('nivelRiesgo')
            ->orderBy('probabilidad')
            ->orderBy('impacto')
            ->get();

        return MatrizCalorCeldaResource::collection($celdas);
    }

    public function show(MatrizCalorCelda $matrizCalorCelda): MatrizCalorCeldaResource
    {
        $this->authorize('view', $matrizCalorCelda);

        return new MatrizCalorCeldaResource($matrizCalorCelda->load('nivelRiesgo'));
    }

    public function update(Request $request, MatrizCalorCelda $matrizCalorCelda): MatrizCalorCeldaResource
    {
        $this->authorize('update', $matrizCalorCelda);

        $validated = $request->validate([
            'nivel_riesgo_id' => ['required', 'exists:niveles_riesgo,id'],
        ]);

        $matrizCalorCelda->update($validated);

        return new MatrizCalorCeldaResource($matrizCalorCelda->load('nivelRiesgo'));
    }
}