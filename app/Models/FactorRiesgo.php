<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FactorRiesgo extends Model
{
    protected $table = 'factores_riesgo';

    protected $fillable = [
        'nombre',
        'slug',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}
