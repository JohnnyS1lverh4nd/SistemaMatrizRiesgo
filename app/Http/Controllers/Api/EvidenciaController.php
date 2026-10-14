<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evidencia;
use App\Services\SeguimientoService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvidenciaController extends Controller
{
    /**
     * GET /api/evidencias/{evidencia}/descargar. Siempre como descarga
     * (attachment) y con nosniff: el navegador no interpreta el archivo
     * como HTML aunque alguien lograra subir contenido malicioso.
     */
    public function descargar(Evidencia $evidencia): StreamedResponse
    {
        $this->authorize('view', $evidencia);

        return Storage::disk(SeguimientoService::DISCO)->download(
            $evidencia->ruta,
            $evidencia->nombre_original,
            [
                'Content-Type' => $evidencia->mime,
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
