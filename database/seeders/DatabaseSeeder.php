<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Solo catálogos: es seguro ejecutarlo en producción (firstOrCreate) y no
     * crea usuarios. El primer Administrador se crea con
     * php artisan usuarios:crear-administrador.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            FactorRiesgoSeeder::class,
            PeriodicidadSeeder::class,
            NivelRiesgoSeeder::class,
            EscalaNivelSeeder::class,
            MatrizCalorCeldaSeeder::class,
        ]);
    }
}
