<?php

namespace App\Services\Reportes;

use App\Enums\EstadoMatriz;
use App\Enums\EstadoTratamiento;
use App\Models\Riesgo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Atraso de seguimiento (decisión oct. 2026): un riesgo de una edición
 * vigente, con tratamiento no cumplido y periodicidad con plazo, está
 * atrasado si no tiene seguimiento (no anulado) en los días de su
 * periodicidad, contados desde el último seguimiento o, si no hay ninguno,
 * desde la publicación de la edición.
 *
 * Se calcula en PHP y no en SQL: la aritmética de fechas difiere entre
 * SQLite, MySQL y PostgreSQL, y el volumen es pequeño (decenas de riesgos por
 * departamento).
 */
class EstadoSeguimiento
{
    /**
     * Relaciones y agregado necesarios para calcular el estado sin consultas
     * adicionales por riesgo.
     *
     * @param  Builder<Riesgo>  $query
     * @return Builder<Riesgo>
     */
    public static function preparar(Builder $query): Builder
    {
        return $query
            ->with(['periodicidad', 'matriz'])
            ->withMax(['seguimientos as ultimo_seguimiento' => fn ($q) => $q->whereNull('anulado_at')], 'fecha');
    }

    /**
     * @return array{ultimo: string|null, vence: string|null, atrasado: bool}
     */
    public static function de(Riesgo $riesgo, ?Carbon $hoy = null): array
    {
        $hoy ??= now();
        $ultimo = $riesgo->getAttribute('ultimo_seguimiento');
        $ultimo = $ultimo !== null ? Carbon::parse($ultimo)->toDateString() : null;

        $dias = $riesgo->periodicidad?->frecuencia_dias;
        $aplica = $dias !== null
            && $riesgo->matriz->estado === EstadoMatriz::Vigente
            && $riesgo->estado_tratamiento !== EstadoTratamiento::Cumplido;

        $referencia = $ultimo !== null ? Carbon::parse($ultimo) : $riesgo->matriz->publicada_at?->copy()->startOfDay();

        if (! $aplica || $referencia === null) {
            return ['ultimo' => $ultimo, 'vence' => null, 'atrasado' => false];
        }

        $vence = $referencia->copy()->addDays($dias);

        return [
            'ultimo' => $ultimo,
            'vence' => $vence->toDateString(),
            'atrasado' => $hoy->copy()->startOfDay()->greaterThan($vence),
        ];
    }
}
