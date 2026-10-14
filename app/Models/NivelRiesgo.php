<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NivelRiesgo extends Model
{
    protected $table = 'niveles_riesgo';

    protected $fillable = [
        'nombre',
        'slug',
        'color',
        'orden',
    ];
}
