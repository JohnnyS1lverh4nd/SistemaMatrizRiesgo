<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Departamento extends Model
{
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
        'direccion',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_departamento');
    }

    /** @return HasMany<Proceso, $this> */
    public function procesos(): HasMany
    {
        return $this->hasMany(Proceso::class);
    }

    /**
     * Departamentos que el usuario puede consultar (ver DepartamentoPolicy::view).
     *
     * @param  Builder<Departamento>  $query
     */
    public function scopeVisiblesPara(Builder $query, User $user): void
    {
        if ($user->puedeConsultarTodosLosDepartamentos()) {
            return;
        }

        $query->whereHas('users', fn (Builder $usuarios) => $usuarios->whereKey($user->id));
    }
}
