<?php

namespace App\Models;

use App\Enums\EstadoTratamiento;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Una entrada del histórico de seguimiento de un riesgo (RF-4).
 *
 * @property int $id
 * @property int $riesgo_id
 * @property int $user_id
 * @property Carbon $fecha
 * @property string $descripcion
 * @property EstadoTratamiento|null $estado_tratamiento_anterior
 * @property EstadoTratamiento|null $estado_tratamiento_nuevo
 * @property Carbon|null $anulado_at
 * @property int|null $anulado_por_id
 * @property string|null $motivo_anulacion
 */
class Seguimiento extends Model
{
    use Auditable;

    /**
     * Ningún campo es asignable desde fuera: SeguimientoService fija cada
     * valor (usuario de la sesión, estados, anulación).
     *
     * @var list<string>
     */
    protected $guarded = ['*'];

    /** Lo único que puede escribirse después de crear el registro: su anulación. */
    private const CAMPOS_DE_ANULACION = ['anulado_at', 'anulado_por_id', 'motivo_anulacion', 'updated_at'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'estado_tratamiento_anterior' => EstadoTratamiento::class,
            'estado_tratamiento_nuevo' => EstadoTratamiento::class,
            'anulado_at' => 'datetime',
        ];
    }

    /**
     * El histórico no se edita ni se borra (requisito de trazabilidad): solo
     * se puede anular una vez, con motivo.
     */
    protected static function booted(): void
    {
        static::updating(function (Seguimiento $seguimiento) {
            $otrosCambios = array_diff(array_keys($seguimiento->getDirty()), self::CAMPOS_DE_ANULACION);

            if ($otrosCambios !== [] || $seguimiento->getRawOriginal('anulado_at') !== null) {
                throw new LogicException("El seguimiento {$seguimiento->id} es histórico: no se modifica (solo puede anularse una vez).");
            }
        });

        static::deleting(function (Seguimiento $seguimiento) {
            throw new LogicException("El seguimiento {$seguimiento->id} es histórico: no se elimina; anúlelo con un motivo.");
        });
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    protected function eventoAuditoria(string $evento, array $cambios): string
    {
        return $evento === 'actualizado' && array_key_exists('anulado_at', $cambios) ? 'anulado' : $evento;
    }

    /** @return BelongsTo<Riesgo, $this> */
    public function riesgo(): BelongsTo
    {
        return $this->belongsTo(Riesgo::class);
    }

    /** @return BelongsTo<User, $this> */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por_id');
    }

    /** @return HasMany<Evidencia, $this> */
    public function evidencias(): HasMany
    {
        return $this->hasMany(Evidencia::class);
    }

    public function estaAnulado(): bool
    {
        return $this->anulado_at !== null;
    }
}
