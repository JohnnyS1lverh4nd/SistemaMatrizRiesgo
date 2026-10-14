<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proceso extends Model
{
    use Auditable;

    /**
     * Igual que el valor por defecto de la columna (ver User::$attributes).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'activo' => true,
    ];

    protected $fillable = [
        'departamento_id',
        'nombre',
        'slug',
        'descripcion',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    /** @return BelongsTo<Departamento, $this> */
    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    /** @return HasMany<Matriz, $this> */
    public function matrices(): HasMany
    {
        return $this->hasMany(Matriz::class);
    }
}
