<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Proceso de un departamento: cada uno tiene su propia matriz de riesgos,
 * versionada en ediciones (Matriz).
 *
 * @property int $id
 * @property int $departamento_id
 * @property int|null $creado_por_id
 * @property string $nombre
 * @property string $slug
 * @property string|null $descripcion
 * @property bool $activo
 */
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

    /**
     * Área afectada, para el historial de movimientos (ver Auditable).
     *
     * @return array{departamento_id?: int|null, proceso_id?: int|null, matriz_id?: int|null}
     */
    public function contextoAuditoria(): array
    {
        return ['departamento_id' => $this->departamento_id, 'proceso_id' => $this->id];
    }

    /** @return BelongsTo<Departamento, $this> */
    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    /**
     * Quién lo creó. No es asignable: lo fija el servidor con el usuario de
     * la sesión (ProcesoController::store), nunca el cuerpo de la petición.
     *
     * @return BelongsTo<User, $this>
     */
    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_id');
    }

    /** @return HasMany<Matriz, $this> */
    public function matrices(): HasMany
    {
        return $this->hasMany(Matriz::class);
    }
}
