<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class NivelRiesgo extends Model
{
    use Auditable;

    protected $table = 'niveles_riesgo';

    protected $fillable = [
        'nombre',
        'slug',
        'color',
        'orden',
    ];
}
