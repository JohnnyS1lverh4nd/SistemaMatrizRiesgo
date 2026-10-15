<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Matriz;
use App\Services\Auditor;
use App\Services\Exportacion\DatosMatriz;
use App\Services\Exportacion\PdfMatriz;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Exportación de una edición con el formato institucional (RF-6).
 * Quien puede ver la edición puede exportarla; cada exportación queda en la
 * auditoría (quién se llevó qué documento y cuándo).
 *   GET /api/matrices/{matriz}/exportar/pdf
 */
class ExportacionController extends Controller
{
    public function __construct(private readonly DatosMatriz $datos) {}

    public function pdf(Matriz $matriz, PdfMatriz $pdf): Response
    {
        $this->authorize('view', $matriz);

        $contenido = $pdf->generar($this->datos->de($matriz));
        Auditor::registrar('exportado', 'matriz', $matriz->id, descripcion: 'PDF');

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->nombreArchivo($matriz, 'pdf').'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** "matriz-de-riesgos-sp-4-3-abastecimiento-abril-junio-2026.pdf" */
    private function nombreArchivo(Matriz $matriz, string $extension): string
    {
        return Str::slug("matriz de riesgos {$matriz->proceso->nombre} {$matriz->nombre}").'.'.$extension;
    }
}
