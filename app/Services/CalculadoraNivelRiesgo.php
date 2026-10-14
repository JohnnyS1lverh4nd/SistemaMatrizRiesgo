<?php

namespace App\Services;

use App\Models\MatrizCalorCelda;
use App\Models\NivelRiesgo;
use InvalidArgumentException;

/**
 * Única fuente de verdad del nivel de riesgo: busca la celda Probabilidad ×
 * Impacto de la matriz de calor configurada por el Administrador.
 * Ver docs/reglas-nivel-riesgo.md.
 */
class CalculadoraNivelRiesgo
{
    public const MINIMO = 1;

    public const MAXIMO = 4;

    /**
     * null si el riesgo aún no está evaluado (sin probabilidad ni impacto).
     */
    public function calcular(?int $probabilidad, ?int $impacto): ?NivelRiesgo
    {
        if ($probabilidad === null && $impacto === null) {
            return null;
        }

        if ($probabilidad === null || $impacto === null) {
            throw new InvalidArgumentException('Probabilidad e impacto se evalúan juntos.');
        }

        foreach (['probabilidad' => $probabilidad, 'impacto' => $impacto] as $nombre => $valor) {
            if ($valor < self::MINIMO || $valor > self::MAXIMO) {
                throw new InvalidArgumentException("La {$nombre} debe estar entre ".self::MINIMO.' y '.self::MAXIMO.", se recibió {$valor}.");
            }
        }

        return MatrizCalorCelda::with('nivelRiesgo')
            ->where(['probabilidad' => $probabilidad, 'impacto' => $impacto])
            ->firstOrFail()
            ->nivelRiesgo;
    }
}
