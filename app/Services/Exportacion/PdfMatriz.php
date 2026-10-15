<?php

namespace App\Services\Exportacion;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;

/**
 * PDF de una edición con el formato institucional (carta, horizontal).
 */
class PdfMatriz
{
    /**
     * @param  array{encabezado: array<string, string>, grupos: array<int, array{titulo: string, columnas: array<int, string>}>, filas: array<int, array<string, mixed>>}  $datos
     */
    public function html(array $datos): string
    {
        return view('exportaciones.matriz', [
            ...$datos,
            // Logo incrustado: DomPDF no carga archivos externos (isRemoteEnabled false, chroot).
            'logo' => 'data:image/png;base64,'.base64_encode((string) file_get_contents(FormatoInstitucional::rutaLogo())),
            'anchos' => FormatoInstitucional::ANCHOS_PDF,
            'fondosGrupos' => FormatoInstitucional::FONDOS_GRUPOS,
            'colorNivel' => fn (?array $nivel) => FormatoInstitucional::colorNivel($nivel),
        ])->render();
    }

    /**
     * @param  array{encabezado: array<string, string>, grupos: array<int, array{titulo: string, columnas: array<int, string>}>, filas: array<int, array<string, mixed>>}  $datos
     */
    public function generar(array $datos): string
    {
        $cache = storage_path('app/dompdf');
        File::ensureDirectoryExists($cache);

        $opciones = new Options([
            // El contenido lo escriben usuarios: sin recursos remotos (SSRF) ni PHP incrustado.
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'isJavascriptEnabled' => false,
            'chroot' => [resource_path('views/exportaciones')],
            // DejaVu incluye acentos y la ñ.
            'defaultFont' => 'DejaVu Sans',
            'fontDir' => $cache,
            'fontCache' => $cache,
            'tempDir' => $cache,
        ]);

        $pdf = new Dompdf($opciones);
        $pdf->loadHtml($this->html($datos), 'UTF-8');
        $pdf->setPaper('letter', 'landscape');
        $pdf->render();

        // Numeración en cada página: "Página X de Y".
        $pdf->getCanvas()->page_text(720, 590, 'Página {PAGE_NUM} de {PAGE_COUNT}', 'DejaVu Sans', 7, [0.35, 0.35, 0.35]);

        return (string) $pdf->output();
    }
}
