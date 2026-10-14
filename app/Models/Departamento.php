<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Departamento extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'slug',
        'direccion',
        'codigo_referencia',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_departamento');
    }

    public function procesos(): HasMany
    {
        return $this->hasMany(Proceso::class);
    }

    /**
     * Departamentos que el usuario puede consultar (ver DepartamentoPolicy::view).
     */
    public function scopeVisiblesPara(Builder $query, User $user): void
    {
        if ($user->puedeConsultarTodosLosDepartamentos()) {
            return;
        }

        $query->whereHas('users', fn (Builder $usuarios) => $usuarios->whereKey($user->id));
    }
}