<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class FactorRiesgo extends Model
{
    use Auditable;

    protected $table = 'factores_riesgo';

    /**
     * Igual que el valor por defecto de la columna (ver User::$attributes).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'activo' => true,
    ];

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
