<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatrizCalorCelda extends Model
{
    use Auditable;

    protected $table = 'matriz_calor_celdas';

    protected $fillable = [
        'probabilidad',
        'impacto',
        'nivel_riesgo_id',
    ];

    /** @return BelongsTo<NivelRiesgo, $this> */
    public function nivelRiesgo(): BelongsTo
    {
        return $this->belongsTo(NivelRiesgo::class);
    }
}
