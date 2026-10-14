<?php

namespace Database\Seeders;

use App\Models\MatrizCalorCelda;
use App\Models\NivelRiesgo;
use Illuminate\Database\Seeder;

class MatrizCalorCeldaSeeder extends Seeder
{
    /**
     * Regla inicial: suma Probabilidad + Impacto.
     *   2–4 Bajo · 5–6 Moderado · 7 Alto · 8 Crítico
     *
     * INFOTEP no dispone de una tabla oficial. Esta regla es la que mejor
     * reproduce sus matrices Excel (13 de 15 filas evaluadas; el producto
     * P×I solo reproducía 3). Ver docs/reglas-nivel-riesgo.md.
     *
     * firstOrCreate: solo crea las celdas que falten. Volver a ejecutar el
     * seeder no pisa los ajustes que el Administrador haga en cada celda.
     */
    public function run(): void
    {
        $niveles = NivelRiesgo::pluck('id', 'slug');

        for ($probabilidad = 1; $probabilidad <= 4; $probabilidad++) {
            for ($impacto = 1; $impacto <= 4; $impacto++) {
                $suma = $probabilidad + $impacto;

                $nivel = match (true) {
                    $suma <= 4 => 'bajo',
                    $suma <= 6 => 'moderado',
                    $suma === 7 => 'alto',
                    default => 'critico',
                };

                MatrizCalorCelda::firstOrCreate(
                    ['probabilidad' => $probabilidad, 'impacto' => $impacto],
                    ['nivel_riesgo_id' => $niveles[$nivel]]
                );
            }
        }
    }
}
