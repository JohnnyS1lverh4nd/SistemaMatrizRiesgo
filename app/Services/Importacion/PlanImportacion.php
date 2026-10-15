<?php

namespace App\Services\Importacion;

/**
 * Resultado de analizar un Excel: lo que se importaría, sin haber escrito
 * nada. Solo un plan sin errores puede ejecutarse.
 *
 * Cada riesgo trae en "origen" la posición del riesgo equivalente de la
 * edición anterior (mismo enlace que crea el clonado, para comparar).
 *
 * @phpstan-type RiesgoPlan array{fila: int, datos: array<string, mixed>, origen: int|null, seguimiento: string|null}
 * @phpstan-type EdicionPlan array{hoja: string, nombre: string, fecha_revision: string|null, riesgos: list<RiesgoPlan>}
 */
final class PlanImportacion
{
    /**
     * @param  list<EdicionPlan>  $ediciones
     * @param  list<string>  $errores
     * @param  list<string>  $avisos
     */
    public function __construct(
        public readonly string $archivo,
        public readonly array $ediciones,
        public readonly array $errores,
        public readonly array $avisos,
    ) {}

    public function esValido(): bool
    {
        return $this->errores === [] && $this->ediciones !== [];
    }

    /**
     * Vista resumida para mostrar antes de confirmar (consola o pantalla).
     *
     * @return array{archivo: string, valido: bool, ediciones: list<array{hoja: string, nombre: string, fecha_revision: string|null, riesgos: int, seguimientos: int, ajustes: int}>, errores: list<string>, avisos: list<string>}
     */
    public function resumen(): array
    {
        return [
            'archivo' => $this->archivo,
            'valido' => $this->esValido(),
            'ediciones' => array_map(fn (array $edicion) => [
                'hoja' => $edicion['hoja'],
                'nombre' => $edicion['nombre'],
                'fecha_revision' => $edicion['fecha_revision'],
                'riesgos' => count($edicion['riesgos']),
                'seguimientos' => count(array_filter($edicion['riesgos'], fn (array $r) => $r['seguimiento'] !== null)),
                'ajustes' => count(array_filter($edicion['riesgos'], fn (array $r) => ($r['datos']['nivel_ajustado_id'] ?? null) !== null)),
            ], $this->ediciones),
            'errores' => $this->errores,
            'avisos' => $this->avisos,
        ];
    }
}
