<?php

/*
 * Genera docs/manual/Manual-de-usuario.pdf a partir de manual-usuario.html.
 * Uso, desde la raíz del repositorio:  php docs/manual/generar-manual.php
 */

require __DIR__.'/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$raiz = dirname(__DIR__, 2);
$logo = 'data:image/png;base64,'.base64_encode((string) file_get_contents($raiz.'/resources/plantillas/logo-infotep.png'));
$meses = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$fecha = $meses[(int) date('n')].' de '.date('Y');

$fuente = str_replace(['{{LOGO}}', '{{FECHA}}'], [$logo, $fecha], (string) file_get_contents(__DIR__.'/manual-usuario.html'));

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
        $lienzo->text(60, 34, 'Sistema de Gestión de Matrices de Riesgo · Manual de usuario', $fuente, 7.5, $gris);
        $lienzo->line(60, 46, 552, 46, [0.925, 0.71, 0.231], 1.2);
        $lienzo->text(60, 760, 'INFOTEP · RT-01-NI-ONA-156', $fuente, 7.5, $gris);
        $lienzo->text(490, 760, "Página {$pagina} de {$total}", $fuente, 7.5, $gris);
    });

    $pdf->addInfo('Title', 'Manual de usuario · Sistema de Gestión de Matrices de Riesgo');
    $pdf->addInfo('Author', 'INFOTEP');

    return $pdf;
}

// Primera pasada sin números; con pdftotext (poppler) se localiza la página de
// cada sección ("N. Título" al inicio de una página) y se genera de nuevo.
$pdf = renderizar($fuente, []);
$paginas = [];
$temporal = tempnam(sys_get_temp_dir(), 'manual');
file_put_contents($temporal, $pdf->output());
exec('pdftotext -v 2>&1', $salida, $codigo);
if ($codigo === 0) {
    $total = (int) shell_exec('pdfinfo '.escapeshellarg($temporal).' | sed -n "s/^Pages: *//p"');
    for ($pagina = 3; $pagina <= $total; $pagina++) {
        $texto = (string) shell_exec("pdftotext -f {$pagina} -l {$pagina} ".escapeshellarg($temporal).' -');
        foreach (preg_split('/\R/', $texto) ?: [] as $linea) {
            if (preg_match('/^([1-9])\. [A-ZÁÉÍÓÚ]/u', trim($linea), $m) && ! isset($paginas[(int) $m[1]])) {
                $paginas[(int) $m[1]] = (string) $pagina;
                break;
            }
        }
    }
    $pdf = renderizar($fuente, $paginas);
} else {
    echo "Aviso: sin pdftotext, el índice sale sin números de página.\n";
}
unlink($temporal);

file_put_contents(__DIR__.'/Manual-de-usuario.pdf', $pdf->output());
echo "Generado: docs/manual/Manual-de-usuario.pdf\n";
