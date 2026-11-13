<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Departamento extends Model
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

    /**
     * Área afectada, para el historial de movimientos (ver Auditable).
     *
     * @return array{departamento_id?: int|null, proceso_id?: int|null, matriz_id?: int|null}
     */
    public function contextoAuditoria(): array
    {
        return ['departamento_id' => $this->id];
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
