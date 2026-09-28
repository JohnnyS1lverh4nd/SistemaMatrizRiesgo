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
            ['nombre' => 'Cuatrimestral', 'slug' => 'cuatrimestral'],
            ['nombre' => 'Anual', 'slug' => 'anual'],
            ['nombre' => 'Cuando ocurra', 'slug' => 'cuando-ocurra'],
        ];

        foreach ($periodicidades as $periodicidad) {
            Periodicidad::updateOrCreate(
                ['slug' => $periodicidad['slug']],
                $periodicidad
            );
        }
    }
}