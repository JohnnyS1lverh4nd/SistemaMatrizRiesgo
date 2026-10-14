<?php

namespace App\Models;

use App\Enums\EscalaTipo;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property EscalaTipo $tipo Cast a enum (ver casts()); Larastan no lo infiere solo.
 */
class EscalaNivel extends Model
{
    use Auditable;

    protected $table = 'escala_niveles';

    protected $fillable = [
        'tipo',
        'valor',
        'nombre',
        'descripcion',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => EscalaTipo::class,
        ];
    }
}
