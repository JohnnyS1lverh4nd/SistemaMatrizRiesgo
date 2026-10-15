<?php

namespace App\Services\Exportacion;

/**
 * Formato visual de la plantilla institucional RT-01-NI-ONA-156, tomado de
 * los Excel originales (hoja "ABRIL-JUNIO 2026" de Compras). Excel y PDF lo
 * comparten: un solo lugar para cambiar colores, anchos o el logo.
 */
final class FormatoInstitucional
{
    /** Encabezado: fila del título y fila de Dirección / Proceso / Fecha. */
    public const FONDO_TITULO = 'F2F2F2';

    public const FONDO_DATOS_ENCABEZADO = 'E7E6E6';

    public const FONDO_COLUMNAS = 'F2F2F2';

    /** Fila 3: un color por grupo de columnas, en el orden de DatosMatriz::GRUPOS. */
    public const FONDOS_GRUPOS = ['F4B184', 'FFD966', 'C5E0B4'];

    /** Columna Seguimiento/ Evidencias: azul y en negrita. */
    public const COLOR_SEGUIMIENTO = '2E75B5';

    /**
     * Niveles de riesgo como en los Excel. Crítico (C00000, "rojo oscuro" de
     * Excel) sale del formato condicional de las plantillas.
     *
     * @var array<string, array{fondo: string, texto: string}>
     */
    public const NIVELES = [
        'bajo' => ['fondo' => 'A9D18E', 'texto' => '000000'],
        'moderado' => ['fondo' => 'FFD966', 'texto' => '000000'],
        'alto' => ['fondo' => 'F4B184', 'texto' => '000000'],
        'critico' => ['fondo' => 'C00000', 'texto' => 'FFFFFF'],
    ];

    /** Ancho de las columnas A–M en caracteres, como en la plantilla. */
    public const ANCHOS = [19.14, 17.86, 18.57, 24.0, 21.57, 18.0, 12.0, 14.29, 23.29, 24.43, 17.71, 15.86, 70.57];

    /**
     * Anchos del PDF en % de una hoja carta horizontal. No son las
     * proporciones del Excel (allí Seguimiento ocupa un 24 %): en papel,
     * Probabilidad, Impacto y Nivel quedarían tan estrechas que partirían
     * las palabras. Seguimiento conserva un 18,5 % para que los textos
     * largos no hagan filas más altas que una página.
     */
    public const ANCHOS_PDF = [6.5, 7.0, 7.0, 8.0, 7.5, 6.5, 4.5, 6.0, 7.0, 8.0, 6.5, 7.0, 18.5];

    /** Fuente del código del formulario en la plantilla (si no está instalada, se sustituye). */
    public const FUENTE_CODIGO = 'INFOTEXT_B';

    public static function rutaLogo(): string
    {
        return resource_path('plantillas/logo-infotep.png');
    }

    /**
     * Colores del nivel. Un nivel que no existe en la plantilla (renombrado o
     * nuevo en el catálogo) usa su color del catálogo.
     *
     * @param  array{slug?: string, color?: string}|null  $nivel
     * @return array{fondo: string, texto: string}|null
     */
    public static function colorNivel(?array $nivel): ?array
    {
        if ($nivel === null) {
            return null;
        }

        return self::NIVELES[$nivel['slug'] ?? ''] ?? [
            'fondo' => strtoupper(ltrim((string) ($nivel['color'] ?? 'FFFFFF'), '#')),
            'texto' => '000000',
        ];
    }
}
