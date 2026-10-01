<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatrizCalorCelda extends Model
{
    use HasFactory;

    protected $table = 'matriz_calor_celdas';

    protected $fillable = [
        'probabilidad',
        'impacto',
        'nivel_riesgo_id',
    ];

    public function nivelRiesgo(): BelongsTo
    {
        return $this->belongsTo(NivelRiesgo::class);
    }
}