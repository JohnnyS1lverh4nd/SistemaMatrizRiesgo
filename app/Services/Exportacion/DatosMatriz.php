<?php

namespace App\Services\Exportacion;

use App\Models\Matriz;
use App\Models\Riesgo;
use App\Models\Seguimiento;

/**
 * Contenido de una edición con el formato institucional RT-01-NI-ONA-156,
 * igual que los Excel originales: encabezado (Dirección, Proceso, código,
 * edición, fecha de revisión), tres grupos de columnas y una fila por riesgo.
 *
 * PDF y Excel dibujan exactamente estos datos: un único formato.
 */
class DatosMatriz
{
    /** Grupos y columnas en el orden de los Excel institucionales. */
    public const GRUPOS = [
        ['titulo' => 'Identificación de Riesgos', 'columnas' => ['Actividad', 'Riesgos', 'Fáctor', 'Causas', 'Control actual']],
        ['titulo' => 'Análisis de Riesgos', 'columnas' => ['Probabilidad', 'Impacto', 'Nivel de riesgo', 'Síntoma']],
        ['titulo' => 'Seguimiento y Control de Riesgos', 'columnas' => ['Tratamiento', 'Responsable', 'Fecha ó Periodicidad', 'Seguimiento/ Evidencias']],
    ];

    /**
     * @return array{encabezado: array<string, string>, grupos: array<int, array{titulo: string, columnas: array<int, string>}>, filas: array<int, array<string, mixed>>}
     */
    public function de(Matriz $matriz): array
    {
        $matriz->loadMissing('proceso.departamento');

        $riesgos = $matriz->riesgos()
            ->with([
                'factores',
                'nivelCalculado',
                'nivelAjustado',
                'periodicidad',
                // Solo seguimientos válidos: los anulados no forman parte del documento.
                'seguimientos' => fn ($q) => $q->whereNull('anulado_at')->withCount('evidencias')->orderBy('fecha')->orderBy('id'),
            ])
            ->get();

        $departamento = $matriz->proceso->departamento;

        return [
            'encabezado' => [
                'titulo' => 'MATRIZ DE RIESGOS',
                'codigo' => (string) config('matrices.formulario.codigo'),
                'edicion_formulario' => (string) config('matrices.formulario.edicion'),
                // Si el departamento no tiene dirección registrada, se usa su nombre.
                'direccion' => $departamento->direccion ?? $departamento->nombre,
                'proceso' => $matriz->proceso->nombre,
                'edicion' => $matriz->nombre,
                'fecha_revision' => $matriz->fecha_revision->copy()->settings(['locale' => 'es'])->translatedFormat('j \d\e F \d\e Y'),
                'estado' => $matriz->estado->value,
            ],
            'grupos' => self::GRUPOS,
            'filas' => $riesgos->map(fn (Riesgo $riesgo) => $this->fila($riesgo))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fila(Riesgo $riesgo): array
    {
        $nivel = $riesgo->nivelEfectivo();

        return [
            'orden' => $riesgo->orden,
            'actividad' => $riesgo->actividad,
            'descripcion' => $riesgo->descripcion,
            // Como en los Excel ("Pnal / Tec"): varios factores separados por barra.
            'factores' => $riesgo->factores->pluck('nombre')->implode(' / '),
            'causas' => $riesgo->causas,
            'control_actual' => $riesgo->control_actual,
            'probabilidad' => $riesgo->probabilidad,
            'impacto' => $riesgo->impacto,
            'nivel' => $nivel ? ['nombre' => $nivel->nombre, 'color' => $nivel->color] : null,
            'nivel_ajustado' => $riesgo->nivel_ajustado_id !== null,
            'justificacion_ajuste' => $riesgo->justificacion_ajuste,
            'sintoma' => $riesgo->sintoma,
            'tratamiento' => $riesgo->tratamiento,
            'responsable' => $riesgo->responsable,
            'periodicidad' => $riesgo->periodicidad?->nombre,
            'seguimiento' => $riesgo->seguimientos
                ->map(fn (Seguimiento $s) => $s->fecha->format('d/m/Y').': '.$s->descripcion
                    .($s->getAttribute('evidencias_count') ? ' ('.$s->getAttribute('evidencias_count').' evidencia(s))' : ''))
                ->implode("\n"),
        ];
    }
}
