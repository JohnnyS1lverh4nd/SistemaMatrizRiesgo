<?php

namespace Database\Seeders;

use App\Enums\EscalaTipo;
use App\Models\EscalaNivel;
use Illuminate\Database\Seeder;

class EscalaNivelSeeder extends Seeder
{
    /**
     * Nombres PROVISIONALES: el PDF de requerimientos solo define los
     * valores 1-4. Deben validarse con los Excel institucionales.
     */
    public function run(): void
    {
        $escalas = [
            EscalaTipo::Probabilidad->value => [
                1 => 'Muy baja',
                2 => 'Baja',
                3 => 'Media',
                4 => 'Alta',
            ],
            EscalaTipo::Impacto->value => [
                1 => 'Insignificante',
                2 => 'Menor',
                3 => 'Moderado',
                4 => 'Mayor',
            ],
        ];

        foreach ($escalas as $tipo => $valores) {
            foreach ($valores as $valor => $nombre) {
                EscalaNivel::updateOrCreate(
                    ['tipo' => $tipo, 'valor' => $valor],
                    ['nombre' => $nombre]
                );
            }
        }
    }
}