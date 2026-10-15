<?php

namespace App\Services\Importacion;

use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Lee un Excel con el formato institucional RT-01-NI-ONA-156 y devuelve, por
 * cada hoja visible con ese formato, el encabezado y una fila plana por
 * riesgo. Solo entiende el formato del archivo; no aplica reglas de negocio
 * (eso lo hace ImportadorMatrices).
 *
 * - Las celdas combinadas se copian a cada fila que abarcan: un riesgo con
 *   varias causas (sub-filas) repite su descripción, probabilidad e impacto.
 * - El texto enriquecido se convierte en texto plano y los espacios se
 *   normalizan. Las fórmulas no se evalúan: se usa el último valor guardado.
 */
class LectorExcel
{
    /** Columnas A–M en el orden del formulario. */
    public const COLUMNAS = [
        'actividad', 'descripcion', 'factor', 'causas', 'control_actual',
        'probabilidad', 'impacto', 'nivel', 'sintoma',
        'tratamiento', 'responsable', 'periodicidad', 'seguimiento',
    ];

    /** Filas en las que se busca la cabecera de columnas ("Actividad"). */
    private const MAX_FILA_CABECERA = 15;

    /**
     * @return list<array{nombre: string, encabezado: array{direccion: ?string, proceso: ?string, fecha_revision: ?string}, filas: list<array<string, mixed>>}>
     *
     * @throws InvalidArgumentException si el archivo no es un Excel legible
     */
    public function leer(string $ruta): array
    {
        $lector = new Xlsx;

        if (! is_file($ruta) || ! $lector->canRead($ruta)) {
            throw new InvalidArgumentException('El archivo no es un archivo Excel válido (.xlsx).');
        }

        try {
            $libro = $lector->load($ruta);
        } catch (Throwable $e) {
            throw new InvalidArgumentException('El archivo no es un archivo Excel válido (.xlsx): '.$e->getMessage(), previous: $e);
        }

        $hojas = [];

        foreach ($libro->getAllSheets() as $hoja) {
            if ($hoja->getSheetState() !== Worksheet::SHEETSTATE_VISIBLE) {
                continue;
            }

            $filaCabecera = $this->filaCabecera($hoja);

            if ($filaCabecera === null) {
                continue;
            }

            $hojas[] = [
                'nombre' => $hoja->getTitle(),
                'encabezado' => $this->encabezado($hoja, $filaCabecera),
                'filas' => $this->filas($hoja, $this->primeraFilaDeDatos($hoja, $filaCabecera)),
            ];
        }

        $libro->disconnectWorksheets();

        return $hojas;
    }

    /**
     * Fila con "Actividad" en A y "Riesgos" en B; null si la hoja no tiene el formato.
     */
    private function filaCabecera(Worksheet $hoja): ?int
    {
        for ($fila = 1; $fila <= self::MAX_FILA_CABECERA; $fila++) {
            $a = $this->clave($this->valor($hoja->getCell("A{$fila}")));
            $b = $this->clave($this->valor($hoja->getCell("B{$fila}")));

            if ($a === 'actividad' && str_starts_with($b, 'riesgo')) {
                return $fila;
            }
        }

        return null;
    }

    /**
     * En el formulario la cabecera de columnas ocupa dos filas combinadas
     * (4 y 5); en el Excel que exporta el sistema, una sola.
     */
    private function primeraFilaDeDatos(Worksheet $hoja, int $filaCabecera): int
    {
        foreach ($hoja->getMergeCells() as $rango) {
            [[$columna, $desde], [, $hasta]] = Coordinate::rangeBoundaries($rango);

            if ((int) $columna === 1 && (int) $desde === $filaCabecera) {
                return (int) $hasta + 1;
            }
        }

        return $filaCabecera + 1;
    }

    /**
     * "DIRECCIÓN: …", "PROCESO: …" y "FECHA DE REVISIÓN: …", estén en la
     * columna que estén por encima de la cabecera de columnas.
     *
     * @return array{direccion: ?string, proceso: ?string, fecha_revision: ?string}
     */
    private function encabezado(Worksheet $hoja, int $filaCabecera): array
    {
        $encabezado = ['direccion' => null, 'proceso' => null, 'fecha_revision' => null];
        $etiquetas = ['direccion' => 'direccion', 'proceso' => 'proceso', 'fecha de revision' => 'fecha_revision'];

        for ($fila = 1; $fila < $filaCabecera; $fila++) {
            foreach ($hoja->getRowIterator($fila, $fila)->current()->getCellIterator('A', 'Z') as $celda) {
                $texto = $this->valor($celda);

                if ($texto === null || ! str_contains($texto, ':')) {
                    continue;
                }

                [$etiqueta, $contenido] = array_map('trim', explode(':', $texto, 2));
                $campo = $etiquetas[$this->clave($etiqueta)] ?? null;

                if ($campo !== null && $contenido !== '') {
                    $encabezado[$campo] = $contenido;
                }
            }
        }

        return $encabezado;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function filas(Worksheet $hoja, int $primera): array
    {
        $ultima = $hoja->getHighestDataRow();
        $propias = [];

        for ($fila = $primera; $fila <= $ultima; $fila++) {
            foreach (self::COLUMNAS as $indice => $campo) {
                $propias[$fila][$campo] = $this->valor($hoja->getCell(Coordinate::stringFromColumnIndex($indice + 1).$fila));
            }
        }

        // Una fila sin contenido propio no es un riesgo, aunque una celda
        // combinada de más arriba la abarque.
        $propias = array_filter($propias, fn (array $valores) => array_filter($valores, fn ($v) => $v !== null) !== []);
        $completas = $propias;

        foreach ($hoja->getMergeCells() as $rango) {
            [[$columnaInicio, $filaInicio], [$columnaFin, $filaFin]] = Coordinate::rangeBoundaries($rango);

            if ($filaFin < $primera || $filaInicio === $filaFin) {
                continue;
            }

            for ($columna = (int) $columnaInicio; $columna <= min((int) $columnaFin, count(self::COLUMNAS)); $columna++) {
                $campo = self::COLUMNAS[$columna - 1];
                $valor = $propias[$filaInicio][$campo] ?? null;

                for ($fila = max((int) $filaInicio, $primera); $fila <= $filaFin; $fila++) {
                    if (isset($completas[$fila])) {
                        $completas[$fila][$campo] = $valor;
                    }
                }
            }
        }

        $resultado = [];

        foreach ($completas as $fila => $valores) {
            $resultado[] = ['fila' => $fila, ...$valores];
        }

        return $resultado;
    }

    private function valor(Cell $celda): ?string
    {
        $valor = $celda->isFormula() ? $celda->getOldCalculatedValue() : $celda->getValue();

        if ($valor instanceof RichText) {
            $valor = $valor->getPlainText();
        }

        if (is_float($valor) && floor($valor) === $valor) {
            $valor = (int) $valor;
        }

        if ($valor === null || is_bool($valor)) {
            return null;
        }

        return $this->normalizar((string) $valor);
    }

    /**
     * Saltos de línea uniformes, sin espacios sobrantes. Las viñetas que en el
     * Excel se separaban con espacios ("…tiempo.        * No planificar")
     * pasan a líneas propias.
     */
    private function normalizar(string $texto): ?string
    {
        $texto = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $texto);
        $texto = (string) preg_replace('/[ \t]{2,}(?=[*•])/u', "\n", $texto);
        $texto = (string) preg_replace('/[ \t]+/u', ' ', $texto);
        $texto = implode("\n", array_map('trim', explode("\n", $texto)));
        $texto = trim((string) preg_replace('/\n{3,}/', "\n\n", $texto));

        return $texto === '' ? null : $texto;
    }

    /** "Fecha de Revisión" -> "fecha de revision" */
    private function clave(?string $texto): string
    {
        return Str::lower(Str::ascii(trim((string) $texto)));
    }
}
