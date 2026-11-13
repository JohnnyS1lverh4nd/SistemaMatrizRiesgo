<?php

/*
 * Genera docs/technical/Informe-tecnico.pdf a partir de informe-tecnico.html.
 * Uso, desde la raíz del repositorio:  php docs/technical/generar-informe.php
 *
 * Mismo mecanismo que docs/manual/generar-manual.php (DomPDF, ya incluido en
 * el proyecto). El índice se numera con pdftotext/pdfinfo (poppler) si están
 * instalados.
 */

require __DIR__.'/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

const ENCABEZADO = 'Sistema de Gestión de Matrices de Riesgo · Informe técnico';

$raiz = dirname(__DIR__, 2);
$logo = 'data:image/png;base64,'.base64_encode((string) file_get_contents($raiz.'/resources/plantillas/logo-infotep.png'));
$meses = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$fecha = $meses[(int) date('n')].' de '.date('Y');

$fuente = str_replace(['{{LOGO}}', '{{FECHA}}'], [$logo, $fecha], (string) file_get_contents(__DIR__.'/informe-tecnico.html'));

/** @param  array<int, string>  $paginas  número de sección => página */
function renderizar(string $fuente, array $paginas): Dompdf
{
    $html = preg_replace_callback('/\{\{PAG_(\d+)\}\}/', fn ($m) => $paginas[(int) $m[1]] ?? '', $fuente);

    $pdf = new Dompdf(new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'defaultFont' => 'DejaVu Sans']));
    $pdf->loadHtml((string) $html, 'UTF-8');
    $pdf->setPaper('letter', 'portrait');
    $pdf->render();

    // Encabezado y pie en todas las páginas salvo la portada.
    $pdf->getCanvas()->page_script(function (int $pagina, int $total, $lienzo, $fuentes) {
        if ($pagina === 1) {
            return;
        }
        $fuente = $fuentes->getFont('DejaVu Sans');
        $gris = [0.42, 0.45, 0.52];
        $lienzo->text(60, 34, ENCABEZADO, $fuente, 7.5, $gris);
        $lienzo->line(60, 46, 552, 46, [0.925, 0.71, 0.231], 1.2);
        $lienzo->text(60, 760, 'INFOTEP · Documento técnico interno', $fuente, 7.5, $gris);
        $lienzo->text(490, 760, "Página {$pagina} de {$total}", $fuente, 7.5, $gris);
    });

    $pdf->addInfo('Title', 'Informe técnico · Sistema de Gestión de Matrices de Riesgo');
    $pdf->addInfo('Author', 'INFOTEP');

    return $pdf;
}

// Primera pasada sin números; con pdftotext se localiza la página de cada
// sección y se genera de nuevo. Cada sección empieza en página nueva, así que
// solo cuenta la primera línea de contenido de la página (tras el encabezado):
// una lista numerada que continúa en otra página no se confunde con una sección.
$pdf = renderizar($fuente, []);
$paginas = [];
$temporal = tempnam(sys_get_temp_dir(), 'informe');
file_put_contents($temporal, $pdf->output());
exec('pdftotext -v 2>&1', $salida, $codigo);
if ($codigo === 0) {
    $total = (int) shell_exec('pdfinfo '.escapeshellarg($temporal).' | sed -n "s/^Pages: *//p"');
    for ($pagina = 3; $pagina <= $total; $pagina++) {
        $texto = (string) shell_exec("pdftotext -f {$pagina} -l {$pagina} ".escapeshellarg($temporal).' -');
        $lineas = array_values(array_filter(
            array_map('trim', preg_split('/\R/', $texto) ?: []),
            fn (string $linea) => $linea !== '' && ! str_starts_with($linea, ENCABEZADO),
        ));
        if (preg_match('/^([1-9][0-9]?)\. [A-ZÁÉÍÓÚ]/u', $lineas[0] ?? '', $m) && ! isset($paginas[(int) $m[1]])) {
            $paginas[(int) $m[1]] = (string) $pagina;
        }
    }
    $pdf = renderizar($fuente, $paginas);
} else {
    echo "Aviso: sin pdftotext, el índice sale sin números de página.\n";
}
unlink($temporal);

file_put_contents(__DIR__.'/Informe-tecnico.pdf', $pdf->output());
echo 'Generado: docs/technical/Informe-tecnico.pdf ('.count($paginas)." secciones localizadas en el índice)\n";
