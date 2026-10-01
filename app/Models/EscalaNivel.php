<?php

namespace App\Models;

use App\Enums\EscalaTipo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EscalaNivel extends Model
{
    use HasFactory;

    protected $table = 'escala_niveles';

    protected $fillable = [
        'tipo',
        'valor',
        'nombre',
        'descripcion',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => EscalaTipo::class,
        ];
    }
}