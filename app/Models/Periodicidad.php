<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Periodicidad extends Model
{
    use Auditable;

    protected $table = 'periodicidades';

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
        'frecuencia_dias',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'frecuencia_dias' => 'integer',
        ];
    }
}
