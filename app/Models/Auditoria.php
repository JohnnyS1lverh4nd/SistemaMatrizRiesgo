<?php

namespace App\Models;

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
}
