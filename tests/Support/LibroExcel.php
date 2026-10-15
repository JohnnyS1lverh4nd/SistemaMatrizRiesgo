<?php

namespace Tests\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Libros Excel con el formato institucional RT-01-NI-ONA-156, construidos en
 * las pruebas con las mismas particularidades que los archivos reales.
 */
class LibroExcel
{
    /**
     * Encabezado institucional (filas 1–5) y riesgos desde la fila 6.
     *
     * @param  array<int, array<int, mixed>>  $filas  13 valores (A–M) por fila
     */
    public static function hoja(Worksheet $hoja, string $fecha, array $filas, string $proceso = 'SP 4.3. Abastecimiento'): Worksheet
    {
        $hoja->setCellValue('A1', 'MATRIZ DE RIESGOS     ');
        $hoja->setCellValue('K1', "RT-01-NI-ONA-156\nEdición 1");
        $hoja->setCellValue('A2', '  DIRECCIÓN: Administración y Finanzas');
        $hoja->setCellValue('C2', "PROCESO: {$proceso}");
        $hoja->setCellValue('J2', $fecha);
        $hoja->setCellValue('A3', 'Identificación de Riesgos');
        $hoja->fromArray(['Actividad', 'Riesgos', 'Fáctor', 'Causas', 'Control actual', 'Probabilidad', 'Impacto', 'Nivel de riesgo', 'Síntoma', 'Tratamiento', 'Responsable', 'Fecha ó Periodicidad', 'Seguimiento/ Evidencias'], null, 'A4');
        foreach (range('A', 'M') as $columna) {
            $hoja->mergeCells("{$columna}4:{$columna}5");
        }
        $hoja->fromArray($filas, null, 'A6', true);

        return $hoja;
    }

    /**
     * Fila A–M a partir de los valores indicados por índice (0 = A).
     *
     * @param  array<int, mixed>  $valores
     * @return array<int, mixed>
     */
    public static function fila(array $valores): array
    {
        return array_replace(array_fill(0, 13, null), $valores);
    }

    /**
     * Fila completa de un riesgo evaluado; $cambios sobrescribe columnas por nombre.
     *
     * @param  array<string, mixed>  $cambios
     * @return array<int, mixed>
     */
    public static function riesgo(array $cambios = []): array
    {
        $base = [
            'actividad' => 'Evaluación de ofertas', 'descripcion' => 'Adjudicaciones erróneas', 'factor' => 'Administrativo',
            'causas' => 'Criterios ambiguos', 'control_actual' => 'Pautas a peritos', 'probabilidad' => 3, 'impacto' => 4,
            'nivel' => 'Alto', 'sintoma' => 'Demandas', 'tratamiento' => 'Revisión de pliegos', 'responsable' => 'Comité de Compras',
            'periodicidad' => 'Revisión cada tres meses', 'seguimiento' => null,
        ];

        return array_values(array_replace($base, $cambios));
    }

    /** @param  callable(Spreadsheet): void  $construir */
    public static function guardar(callable $construir): string
    {
        $libro = new Spreadsheet;
        $construir($libro);
        $ruta = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        (new Xlsx($libro))->save($ruta);

        return $ruta;
    }

    public static function limpiar(): void
    {
        foreach (glob(sys_get_temp_dir().'/imp*') ?: [] as $archivo) {
            @unlink($archivo);
        }
    }
}
