<?php

namespace Database\Seeders;

use App\Models\MatrizCalorCelda;
use App\Models\NivelRiesgo;
use Illuminate\Database\Seeder;

class MatrizCalorCeldaSeeder extends Seeder
{
    /**
     * Umbrales PROVISIONALES sobre el producto Probabilidad x Impacto.
     * Deben validarse contra los Excel institucionales antes de cerrar
     * la Fase 4 — un Excel real podría usar una tabla con excepciones
     * en vez de esta regla matemática simple.
     */
    public function run(): void
    {
        $bajo = NivelRiesgo::where('slug', 'bajo')->firstOrFail();
        $moderado = NivelRiesgo::where('slug', 'moderado')->firstOrFail();
        $alto = NivelRiesgo::where('slug', 'alto')->firstOrFail();
        $critico = NivelRiesgo::where('slug', 'critico')->firstOrFail();

        for ($probabilidad = 1; $probabilidad <= 4; $probabilidad++) {
            for ($impacto = 1; $impacto <= 4; $impacto++) {
                $puntaje = $probabilidad * $impacto;

                $nivel = match (true) {
                    $puntaje <= 3 => $bajo,
                    $puntaje <= 6 => $moderado,
                    $puntaje <= 9 => $alto,
                    default => $critico,
                };

                MatrizCalorCelda::updateOrCreate(
                    ['probabilidad' => $probabilidad, 'impacto' => $impacto],
                    ['nivel_riesgo_id' => $nivel->id]
                );
            }
        }
    }
}