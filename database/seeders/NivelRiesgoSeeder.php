<?php

namespace Database\Seeders;

use App\Models\NivelRiesgo;
use Illuminate\Database\Seeder;

class NivelRiesgoSeeder extends Seeder
{
    public function run(): void
    {
        $niveles = [
            ['nombre' => 'Bajo', 'slug' => 'bajo', 'color' => '#22c55e', 'orden' => 1],
            ['nombre' => 'Moderado', 'slug' => 'moderado', 'color' => '#eab308', 'orden' => 2],
            ['nombre' => 'Alto', 'slug' => 'alto', 'color' => '#f97316', 'orden' => 3],
            ['nombre' => 'Crítico', 'slug' => 'critico', 'color' => '#ef4444', 'orden' => 4],
        ];

        foreach ($niveles as $nivel) {
            NivelRiesgo::updateOrCreate(
                ['slug' => $nivel['slug']],
                $nivel
            );
        }
    }
}