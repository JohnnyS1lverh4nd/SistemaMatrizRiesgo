<?php

namespace Database\Seeders;

use App\Models\Periodicidad;
use Illuminate\Database\Seeder;

class PeriodicidadSeeder extends Seeder
{
    public function run(): void
    {
        $periodicidades = [
            ['nombre' => 'Diario', 'slug' => 'diario'],
            ['nombre' => 'Mensual', 'slug' => 'mensual'],
            // "Revisión cada tres meses" es la periodicidad más usada en los Excel.
            ['nombre' => 'Trimestral', 'slug' => 'trimestral'],
            ['nombre' => 'Cuatrimestral', 'slug' => 'cuatrimestral'],
            ['nombre' => 'Anual', 'slug' => 'anual'],
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
