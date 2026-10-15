<?php

namespace App\Services\Exportacion;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Excel (.xlsx) de una edición con el formato institucional, misma
 * distribución que los archivos originales y que el PDF:
 *   fila 1  título + código del formulario
 *   fila 2  Dirección · Proceso · Fecha de revisión y edición
 *   fila 3  marca BORRADOR (vacía si la edición está publicada)
 *   fila 4  grupos de columnas
 *   fila 5  columnas
 *   fila 6… un riesgo por fila
 */
class ExcelMatriz
{
    private const FILA_GRUPOS = 4;

    private const FILA_COLUMNAS = 5;

    private const PRIMERA_FILA_RIESGO = 6;

    /** Ancho de cada columna (A–M), en caracteres. */
    private const ANCHOS = [22, 26, 16, 28, 26, 12, 10, 16, 24, 28, 22, 18, 30];

    /**
     * @param  array{encabezado: array<string, string>, grupos: array<int, array{titulo: string, columnas: array<int, string>}>, filas: array<int, array<string, mixed>>}  $datos
     */
    public function generar(array $datos): string
    {
        $libro = new Spreadsheet;
        $libro->getProperties()
            ->setTitle("Matriz de riesgos · {$datos['encabezado']['proceso']} · {$datos['encabezado']['edicion']}")
            ->setCreator((string) config('app.name'));

        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Matriz de riesgos');

        $this->encabezado($hoja, $datos['encabezado']);
        $this->columnas($hoja, $datos['grupos']);
        $ultimaFila = $this->riesgos($hoja, $datos['filas']);
        $this->formato($hoja, $ultimaFila);

        ob_start();
        (new Xlsx($libro))->save('php://output');

        return (string) ob_get_clean();
    }

    /**
     * @param  array<string, string>  $encabezado
     */
    private function encabezado(Worksheet $hoja, array $encabezado): void
    {
        $this->texto($hoja, 'A1', $encabezado['titulo']);
        $this->texto($hoja, 'J1', "{$encabezado['codigo']} {$encabezado['edicion_formulario']}");
        $this->texto($hoja, 'A2', "DIRECCIÓN: {$encabezado['direccion']}");
        $this->texto($hoja, 'E2', "PROCESO: {$encabezado['proceso']}");
        $this->texto($hoja, 'J2', "FECHA DE REVISIÓN: {$encabezado['fecha_revision']}\nEDICIÓN: {$encabezado['edicion']}");

        foreach (['A1:I1', 'J1:M1', 'A2:D2', 'E2:I2', 'J2:M2', 'A3:M3'] as $rango) {
            $hoja->mergeCells($rango);
        }

        $hoja->getStyle('A1:M1')->getFont()->setBold(true)->setSize(14);
        $hoja->getStyle('A1:M1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $hoja->getStyle('A2:M2')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        $hoja->getRowDimension(2)->setRowHeight(30);

        if ($encabezado['estado'] === 'borrador') {
            $this->texto($hoja, 'A3', 'BORRADOR — documento sin publicar');
            $hoja->getStyle('A3')->getFont()->setBold(true)->getColor()->setRGB('B91C1C');
            $hoja->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
    }

    /**
     * @param  array<int, array{titulo: string, columnas: array<int, string>}>  $grupos
     */
    private function columnas(Worksheet $hoja, array $grupos): void
    {
        $columna = 1;

        foreach ($grupos as $grupo) {
            $inicio = $columna;

            foreach ($grupo['columnas'] as $titulo) {
                $this->texto($hoja, Coordinate::stringFromColumnIndex($columna).self::FILA_COLUMNAS, $titulo);
                $columna++;
            }

            $desde = Coordinate::stringFromColumnIndex($inicio).self::FILA_GRUPOS;
            $this->texto($hoja, $desde, $grupo['titulo']);
            $hoja->mergeCells($desde.':'.Coordinate::stringFromColumnIndex($columna - 1).self::FILA_GRUPOS);
        }

        $cabecera = $hoja->getStyle('A'.self::FILA_GRUPOS.':M'.self::FILA_COLUMNAS);
        $cabecera->getFont()->setBold(true);
        $cabecera->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $hoja->getStyle('A'.self::FILA_GRUPOS.':M'.self::FILA_GRUPOS)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');
        $hoja->getStyle('A'.self::FILA_COLUMNAS.':M'.self::FILA_COLUMNAS)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F3F4F6');
    }

    /**
     * @param  array<int, array<string, mixed>>  $filas
     * @return int última fila escrita
     */
    private function riesgos(Worksheet $hoja, array $filas): int
    {
        if ($filas === []) {
            $fila = self::PRIMERA_FILA_RIESGO;
            $this->texto($hoja, "A{$fila}", 'Esta edición no tiene riesgos registrados.');
            $hoja->mergeCells("A{$fila}:M{$fila}");

            return $fila;
        }

        $fila = self::PRIMERA_FILA_RIESGO;

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

        return $fila - 1;
    }

    /**
     * @param  array<string, mixed>  $riesgo
     */
    private function nivel(Worksheet $hoja, string $celda, array $riesgo): void
    {
        /** @var array{nombre: string, color: string}|null $nivel */
        $nivel = $riesgo['nivel'];

        if ($nivel === null) {
            $this->texto($hoja, $celda, 'Sin evaluar');

            return;
        }

        $texto = $nivel['nombre'];

        if ($riesgo['nivel_ajustado']) {
            $texto .= "\nAjustado: ".$riesgo['justificacion_ajuste'];
        }

        // El color acompaña al nombre del nivel, como en el mapa de calor.
        $this->texto($hoja, $celda, $texto);
        $hoja->getStyle($celda)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(strtoupper(ltrim($nivel['color'], '#')));
        $hoja->getStyle($celda)->getFont()->setBold(true);
    }

    private function formato(Worksheet $hoja, int $ultimaFila): void
    {
        foreach (self::ANCHOS as $indice => $ancho) {
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($indice + 1))->setWidth($ancho);
        }

        $tabla = $hoja->getStyle('A'.self::FILA_GRUPOS.":M{$ultimaFila}");
        $tabla->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('6B7280');

        $cuerpo = $hoja->getStyle('A'.self::PRIMERA_FILA_RIESGO.":M{$ultimaFila}");
        $cuerpo->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $hoja->getStyle('F'.self::PRIMERA_FILA_RIESGO.":G{$ultimaFila}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Encabezados fijos al desplazarse y repetidos en cada página impresa.
        $hoja->freezePane('A'.self::PRIMERA_FILA_RIESGO);
        $hoja->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_LETTER)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd(self::FILA_GRUPOS, self::FILA_COLUMNAS);
        $hoja->getHeaderFooter()->setOddFooter('&RPágina &P de &N');
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
