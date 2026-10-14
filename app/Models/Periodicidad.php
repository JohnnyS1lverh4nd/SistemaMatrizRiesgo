<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Periodicidad extends Model
{
    protected $table = 'periodicidades';

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
