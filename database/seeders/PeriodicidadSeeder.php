<?php

namespace Database\Seeders;

use App\Models\Periodicidad;
use Illuminate\Database\Seeder;

class PeriodicidadSeeder extends Seeder
{
    public function run(): void
    {
        // frecuencia_dias: plazo para registrar seguimiento (null = sin plazo).
        $periodicidades = [
            ['nombre' => 'Diario', 'slug' => 'diario', 'frecuencia_dias' => 1],
            ['nombre' => 'Mensual', 'slug' => 'mensual', 'frecuencia_dias' => 30],
            // "Revisión cada tres meses" es la periodicidad más usada en los Excel.
            ['nombre' => 'Trimestral', 'slug' => 'trimestral', 'frecuencia_dias' => 90],
            ['nombre' => 'Cuatrimestral', 'slug' => 'cuatrimestral', 'frecuencia_dias' => 120],
            ['nombre' => 'Anual', 'slug' => 'anual', 'frecuencia_dias' => 365],
            ['nombre' => 'Cuando ocurra', 'slug' => 'cuando-ocurra'],
        ];

        foreach ($periodicidades as $periodicidad) {
            Periodicidad::firstOrCreate(
                ['slug' => $periodicidad['slug']],
                $periodicidad
            );
        }
    }
}
