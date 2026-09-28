<?php

namespace Database\Seeders;

use App\Models\FactorRiesgo;
use Illuminate\Database\Seeder;

class FactorRiesgoSeeder extends Seeder
{
    public function run(): void
    {
        $factores = [
            ['nombre' => 'Administrativo', 'slug' => 'administrativo'],
            ['nombre' => 'Personal', 'slug' => 'personal'],
            ['nombre' => 'Tecnológico', 'slug' => 'tecnologico'],
            ['nombre' => 'Político-social-ambiental', 'slug' => 'politico-social-ambiental'],
            ['nombre' => 'Interno', 'slug' => 'interno'],
            ['nombre' => 'Externo', 'slug' => 'externo'],
        ];

        foreach ($factores as $factor) {
            FactorRiesgo::updateOrCreate(
                ['slug' => $factor['slug']],
                $factor
            );
        }
    }
}
