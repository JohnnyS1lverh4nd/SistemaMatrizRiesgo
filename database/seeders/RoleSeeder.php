<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $roles = [
            [
                'nombre' => 'Administrador',
                'slug' => 'administrador',
                'descripcion' => 'Gestiona catálogos (procesos, factores de riesgo, escalas), usuarios, permisos y la matriz de calor Probabilidad x Impacto.',
            ],
            [
                'nombre' => 'Encargado de Departamento/Proceso',
                'slug' => 'encargado-departamento',
                'descripcion' => 'Crea y edita la matriz de riesgo de su departamento, registra riesgos y da seguimiento. Solo accede a su(s) propio(s) departamento(s).',
            ],
            [
                'nombre' => 'Encargado de Seguimiento y Control',
                'slug' => 'encargado-seguimiento',
                'descripcion' => 'Actualiza el estado de los tratamientos y registra evidencias de seguimiento.',
            ],
            [
                'nombre' => 'Auditor/Consulta',
                'slug' => 'auditor',
                'descripcion' => 'Acceso de solo lectura a matrices, histórico de ediciones y reportes, sin permisos de edición.',
            ],
            [
                'nombre' => 'Dirección/Gerencia',
                'slug' => 'direccion',
                'descripcion' => 'Consulta dashboards y reportes consolidados por dirección/proceso.',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['slug' => $role['slug']],
                $role
            );
        }
    }
}