<?php

namespace App\Services\Exportacion;

use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Excel (.xlsx) de una edición con la misma hoja que las plantillas
 * institucionales RT-01-NI-ONA-156 (FormatoInstitucional):
 *   fila 1  logo · "MATRIZ DE RIESGOS" · [BORRADOR] · código del formulario
 *   fila 2  Dirección · Proceso · Fecha de revisión
 *   fila 3  grupos de columnas (naranja, amarillo, verde)
 *   filas 4-5  columnas
 *   fila 6… un riesgo por fila
 * Como en los originales, la edición es el nombre de la hoja. Un archivo
 * exportado se puede volver a importar (ImportadorMatrices).
 */
class ExcelMatriz
{
    private const ULTIMA_COLUMNA = 'M';

    private const PRIMERA_FILA_RIESGO = 6;

    /** Caracteres que Excel no admite en el nombre de una hoja. */
    private const NO_VALIDOS_EN_HOJA = ['\\', '/', '?', '*', '[', ']', ':'];

    /**
     * @param  array{encabezado: array<string, string>, grupos: array<int, array{titulo: string, columnas: array<int, string>}>, filas: array<int, array<string, mixed>>}  $datos
     */
    public function generar(array $datos): string
    {
        $libro = new Spreadsheet;
        $libro->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        $libro->getProperties()
            ->setTitle("Matriz de riesgos · {$datos['encabezado']['proceso']} · {$datos['encabezado']['edicion']}")
            ->setCreator((string) config('app.name'));

        $hoja = $libro->getActiveSheet();
        $hoja->setTitle($this->nombreHoja($datos['encabezado']['edicion']));

        $this->encabezado($hoja, $datos['encabezado']);
        $this->columnas($hoja, $datos['grupos']);
        $ultimaFila = $this->riesgos($hoja, $datos['filas']);
        $this->formato($hoja, $ultimaFila);
        $this->logo($hoja);

        ob_start();
        (new Xlsx($libro))->save('php://output');

        return (string) ob_get_clean();
    }

    /**
     * @param  array<string, string>  $encabezado
     */
    private function encabezado(Worksheet $hoja, array $encabezado): void
    {
        foreach (['A1:I1', 'K1:M1', 'A2:B2', 'C2:I2', 'J2:M2'] as $rango) {
            $hoja->mergeCells($rango);
        }

        // Fila 1: título y código del formulario.
        $this->texto($hoja, 'A1', $encabezado['titulo']);
        $this->texto($hoja, 'K1', "{$encabezado['codigo']}\n{$encabezado['edicion_formulario']}");
        $fila1 = $hoja->getStyle('A1:M1');
        $fila1->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(FormatoInstitucional::FONDO_TITULO);
        $fila1->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $hoja->getStyle('A1')->getFont()->setSize(36);
        $hoja->getStyle('K1')->getFont()->setName(FormatoInstitucional::FUENTE_CODIGO)->setSize(16);
        $hoja->getStyle('K1')->getAlignment()->setWrapText(true);
        $hoja->getRowDimension(1)->setRowHeight(46.5);

        // La plantilla deja J1 libre: ahí va la marca de borrador.
        if ($encabezado['estado'] === 'borrador') {
            $this->texto($hoja, 'J1', 'BORRADOR');
            $hoja->getStyle('J1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('C00000');
        }

        // Fila 2: Dirección, Proceso y Fecha de revisión.
        $this->texto($hoja, 'A2', "DIRECCIÓN: {$encabezado['direccion']}");
        $this->texto($hoja, 'C2', "PROCESO: {$encabezado['proceso']}");
        $this->texto($hoja, 'J2', "FECHA DE REVISIÓN: {$encabezado['fecha_revision']}");
        $fila2 = $hoja->getStyle('A2:M2');
        $fila2->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(FormatoInstitucional::FONDO_DATOS_ENCABEZADO);
        $fila2->getFont()->setBold(true)->setSize(10);
        // Altura fija de la plantilla: el texto largo se reduce en lugar de cortarse.
        $fila2->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER)->setShrinkToFit(true);
        $hoja->getStyle('C2')->getFont()->setSize(12);
        $hoja->getRowDimension(2)->setRowHeight(22.5);
    }

    /**
     * Fila 3: grupos con su color. Filas 4-5: una columna por celda combinada.
     *
     * @param  array<int, array{titulo: string, columnas: array<int, string>}>  $grupos
     */
    private function columnas(Worksheet $hoja, array $grupos): void
    {
        $columna = 1;

        foreach ($grupos as $indice => $grupo) {
            $inicio = Coordinate::stringFromColumnIndex($columna);

            foreach ($grupo['columnas'] as $titulo) {
                $letra = Coordinate::stringFromColumnIndex($columna);
                $this->texto($hoja, "{$letra}4", $titulo);
                $hoja->mergeCells("{$letra}4:{$letra}5");
                $columna++;
            }

            $fin = Coordinate::stringFromColumnIndex($columna - 1);
            $this->texto($hoja, "{$inicio}3", $grupo['titulo']);
            $hoja->mergeCells("{$inicio}3:{$fin}3");
            $hoja->getStyle("{$inicio}3:{$fin}3")->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB(FormatoInstitucional::FONDOS_GRUPOS[$indice] ?? FormatoInstitucional::FONDO_COLUMNAS);
        }

        $hoja->getStyle('A3:M3')->getFont()->setBold(true)->setSize(14);
        $hoja->getStyle('A3:M3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $hoja->getRowDimension(3)->setRowHeight(18.75);

        $cabecera = $hoja->getStyle('A4:M5');
        $cabecera->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(FormatoInstitucional::FONDO_COLUMNAS);
        $cabecera->getFont()->setBold(true);
        $cabecera->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $hoja->getRowDimension(5)->setRowHeight(15.75);
    }

    /**
     * @param  array<int, array<string, mixed>>  $filas
     * @return int última fila escrita
     */
    private function riesgos(Worksheet $hoja, array $filas): int
    {
        $fila = self::PRIMERA_FILA_RIESGO;

        if ($filas === []) {
            $this->texto($hoja, "A{$fila}", 'Esta edición no tiene riesgos registrados.');
            $hoja->mergeCells("A{$fila}:M{$fila}");
            $hoja->getStyle("A{$fila}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            return $fila;
        }

        foreach ($filas as $riesgo) {
            $this->texto($hoja, "A{$fila}", $riesgo['actividad']);
            $this->texto($hoja, "B{$fila}", $riesgo['descripcion']);
            $this->texto($hoja, "C{$fila}", $riesgo['factores']);
            $this->texto($hoja, "D{$fila}", $riesgo['causas']);
            $this->texto($hoja, "E{$fila}", $riesgo['control_actual']);
            $this->numero($hoja, "F{$fila}", $riesgo['probabilidad']);
            $this->numero($hoja, "G{$fila}", $riesgo['impacto']);
            $this->nivel($hoja, "H{$fila}", $riesgo);
            $this->texto($hoja, "I{$fila}", $riesgo['sintoma']);
            $this->texto($hoja, "J{$fila}", $riesgo['tratamiento']);
            $this->texto($hoja, "K{$fila}", $riesgo['responsable']);
            $this->texto($hoja, "L{$fila}", $riesgo['periodicidad']);
            $this->texto($hoja, "M{$fila}", $riesgo['seguimiento']);
            $fila++;
        }

        $ultima = $fila - 1;
        $primera = self::PRIMERA_FILA_RIESGO;

        // Alineación de la plantilla: texto a la izquierda, P e I y nivel centrados.
        $hoja->getStyle("A{$primera}:M{$ultima}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $hoja->getStyle("F{$primera}:H{$ultima}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $hoja->getStyle("M{$primera}:M{$ultima}")->getFont()->setBold(true)->setSize(12)
            ->getColor()->setRGB(FormatoInstitucional::COLOR_SEGUIMIENTO);

        return $ultima;
    }

    /**
     * @param  array<string, mixed>  $riesgo
     */
    private function nivel(Worksheet $hoja, string $celda, array $riesgo): void
    {
        /** @var array{nombre: string, slug: string, color: string}|null $nivel */
        $nivel = $riesgo['nivel'];
        $colores = FormatoInstitucional::colorNivel($nivel);

        if ($nivel === null || $colores === null) {
            $this->texto($hoja, $celda, 'Sin evaluar');

            return;
        }

        $texto = $nivel['nombre'];

        if ($riesgo['nivel_ajustado']) {
            $texto .= "\nAjustado: ".$riesgo['justificacion_ajuste'];
        }

        $this->texto($hoja, $celda, $texto);
        $estilo = $hoja->getStyle($celda);
        $estilo->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($colores['fondo']);
        $estilo->getFont()->setBold(true)->getColor()->setRGB($colores['texto']);
    }

    private function formato(Worksheet $hoja, int $ultimaFila): void
    {
        foreach (FormatoInstitucional::ANCHOS as $indice => $ancho) {
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($indice + 1))->setWidth($ancho);
        }

        $hoja->getStyle('A1:'.self::ULTIMA_COLUMNA.$ultimaFila)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('000000');

        // Encabezado fijo al desplazarse y repetido en cada página impresa.
        $hoja->freezePane('A'.self::PRIMERA_FILA_RIESGO);
        $hoja->getSheetView()->setZoomScale(60);
        $hoja->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_LETTER)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd(1, 5)
            ->setPrintArea('A1:'.self::ULTIMA_COLUMNA.$ultimaFila);
        $hoja->getHeaderFooter()->setOddFooter('&RPágina &P de &N');
    }

    /** Logo de la institución en la esquina superior izquierda, dentro de la fila del título. */
    private function logo(Worksheet $hoja): void
    {
        $logo = new Drawing;
        // setPath() primero: es de Drawing; los demás métodos son de la clase base.
        $logo->setPath(FormatoInstitucional::rutaLogo());
        $logo->setName('Logo INFOTEP')
            ->setDescription('INFOTEP')
            ->setCoordinates('A1')
            ->setOffsetX(10)
            ->setOffsetY(4)
            ->setHeight(54)
            ->setWorksheet($hoja);
    }

    /** "Ene/Mar 2026" → "Ene-Mar 2026"; máximo 31 caracteres, como exige Excel. */
    private function nombreHoja(string $edicion): string
    {
        $nombre = str_replace('/', '-', $edicion);
        $nombre = Str::squish(str_replace(self::NO_VALIDOS_EN_HOJA, ' ', $nombre));
        $nombre = trim(mb_substr($nombre, 0, 31), " '");

        return $nombre === '' ? 'Matriz de riesgos' : $nombre;
    }

    /**
     * Siempre como texto explícito: el contenido lo escriben usuarios y un
     * valor que empiece por "=" se ejecutaría como fórmula al abrir el archivo.
     */
    private function texto(Worksheet $hoja, string $celda, mixed $valor): void
    {
        if ($valor === null || $valor === '') {
            return;
        }

        $hoja->getCell($celda)->setValueExplicit((string) $valor, DataType::TYPE_STRING);
    }

    private function numero(Worksheet $hoja, string $celda, mixed $valor): void
    {
        if ($valor !== null) {
            $hoja->getCell($celda)->setValueExplicit((int) $valor, DataType::TYPE_NUMERIC);
        }
    }
}
