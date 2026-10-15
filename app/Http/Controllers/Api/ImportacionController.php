<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Importacion\ImportarMatricesRequest;
use App\Http\Resources\MatrizResource;
use App\Models\Proceso;
use App\Services\Importacion\ImportadorMatrices;
use App\Services\Importacion\PlanImportacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Importación de matrices desde Excel (RF-7). Sin estado en el servidor: la
 * vista previa y la confirmación reciben el archivo y lo analizan cada vez,
 * así se importa exactamente lo que se acaba de validar.
 *   POST /api/procesos/{proceso}/importacion/vista-previa
 *   POST /api/procesos/{proceso}/importacion
 */
class ImportacionController extends Controller
{
    public function __construct(private readonly ImportadorMatrices $importador) {}

    public function vistaPrevia(ImportarMatricesRequest $request, Proceso $proceso): JsonResponse
    {
        return response()->json(['data' => $this->analizar($request, $proceso)->resumen()]);
    }

    public function store(ImportarMatricesRequest $request, Proceso $proceso): JsonResponse
    {
        $plan = $this->analizar($request, $proceso);

        if (! $plan->esValido()) {
            throw ValidationException::withMessages(['archivo' => $plan->errores]);
        }

        $ediciones = $this->importador->ejecutar($plan, $proceso, $request->user());

        return MatrizResource::collection($ediciones)->response()->setStatusCode(201);
    }

    private function analizar(ImportarMatricesRequest $request, Proceso $proceso): PlanImportacion
    {
        $archivo = $request->file('archivo');

        return $this->importador->analizar($archivo->getRealPath(), $proceso, $archivo->getClientOriginalName());
    }
}
