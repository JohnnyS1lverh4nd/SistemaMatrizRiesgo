<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Entrada del registro de auditoría (RF-9). Solo se inserta, mediante
 * App\Services\Auditor; nunca se modifica ni se borra desde la aplicación.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $evento
 * @property string|null $entidad
 * @property int|null $entidad_id
 * @property int|null $departamento_id
 * @property int|null $proceso_id
 * @property int|null $matriz_id
 * @property array<string, mixed>|null $valores_anteriores
 * @property array<string, mixed>|null $valores_nuevos
 * @property string|null $descripcion
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon $created_at
 */
class Auditoria extends Model
{
    protected $table = 'auditoria';

    /** Solo created_at: un registro de auditoría no se actualiza. */
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'valores_anteriores' => 'array',
            'valores_nuevos' => 'array',
            'entidad_id' => 'integer',
            'departamento_id' => 'integer',
            'proceso_id' => 'integer',
            'matriz_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (Auditoria $registro) => throw new LogicException("El registro de auditoría {$registro->id} no se modifica."));
        static::deleting(fn (Auditoria $registro) => throw new LogicException("El registro de auditoría {$registro->id} no se elimina."));
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Departamento, $this> */
    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    /** @return BelongsTo<Proceso, $this> */
    public function proceso(): BelongsTo
    {
        return $this->belongsTo(Proceso::class);
    }

    /**
     * Incluye ediciones descartadas: su historial se sigue consultando.
     *
     * @return BelongsTo<Matriz, $this>
     */
    public function matriz(): BelongsTo
    {
        return $this->belongsTo(Matriz::class)->withTrashed();
    }

    /**
     * Historial de movimientos: registros con área dentro de los departamentos
     * que el usuario puede consultar (misma regla que DepartamentoPolicy::view).
     * Quién puede usar el historial lo decide AuditoriaPolicy::verMovimientos.
     *
     * @param  Builder<Auditoria>  $query
     */
    public function scopeMovimientosVisiblesPara(Builder $query, User $user): void
    {
        $query->whereIn('departamento_id', Departamento::visiblesPara($user)->select('departamentos.id'));
    }
}
