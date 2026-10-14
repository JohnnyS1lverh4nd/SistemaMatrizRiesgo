<?php

namespace App\Models;

use App\Enums\EstadoMatriz;
use App\Enums\EstadoTratamiento;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * Un riesgo de una edición de matriz (una fila del Excel).
 *
 * @property int $id
 * @property int $matriz_id
 * @property int|null $riesgo_origen_id
 * @property int $orden
 * @property string $actividad
 * @property string $descripcion
 * @property int|null $probabilidad
 * @property int|null $impacto
 * @property int|null $nivel_calculado_id
 * @property int|null $nivel_ajustado_id
 * @property string|null $justificacion_ajuste
 * @property EstadoTratamiento $estado_tratamiento
 */
class Riesgo extends Model
{
    use Auditable, SoftDeletes;

    /**
     * nivel_calculado_id, orden y riesgo_origen_id no son asignables:
     * los fija RiesgoService.
     */
    protected $fillable = [
        'actividad',
        'descripcion',
        'causas',
        'control_actual',
        'probabilidad',
        'impacto',
        'nivel_ajustado_id',
        'justificacion_ajuste',
        'sintoma',
        'tratamiento',
        'responsable',
        'periodicidad_id',
        'estado_tratamiento',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'estado_tratamiento' => 'pendiente',
    ];

    /**
     * Lo único que puede cambiar en un riesgo de una edición vigente
     * (seguimiento, Fase 6).
     */
    private const CAMPOS_DE_SEGUIMIENTO = ['estado_tratamiento', 'updated_at'];

    protected function casts(): array
    {
        return [
            'probabilidad' => 'integer',
            'impacto' => 'integer',
            'estado_tratamiento' => EstadoTratamiento::class,
        ];
    }

    /**
     * Defensa en profundidad (además de RiesgoPolicy): los riesgos solo se
     * crean, modifican o eliminan mientras su edición es borrador.
     */
    protected static function booted(): void
    {
        static::creating(function (Riesgo $riesgo) {
            $riesgo->exigirEdicionEnBorrador('agregar riesgos');
        });

        static::updating(function (Riesgo $riesgo) {
            $estado = $riesgo->estadoDeSuEdicion();
            $otrosCambios = array_diff(array_keys($riesgo->getDirty()), self::CAMPOS_DE_SEGUIMIENTO);

            if ($estado === EstadoMatriz::Archivada
                || ($estado === EstadoMatriz::Vigente && $otrosCambios !== [])) {
                throw new LogicException("El riesgo {$riesgo->id} pertenece a una edición {$estado->value} y no admite esos cambios.");
            }
        });

        static::deleting(function (Riesgo $riesgo) {
            $riesgo->exigirEdicionEnBorrador('eliminar riesgos');
        });
    }

    private function exigirEdicionEnBorrador(string $accion): void
    {
        $estado = $this->estadoDeSuEdicion();

        if ($estado !== EstadoMatriz::Borrador) {
            throw new LogicException("No se puede {$accion} en una edición {$estado->value}.");
        }
    }

    /** Consulta el estado actual en la base (no el de una relación ya cargada). */
    private function estadoDeSuEdicion(): EstadoMatriz
    {
        $estado = Matriz::withTrashed()->whereKey($this->matriz_id)->value('estado');

        return $estado instanceof EstadoMatriz ? $estado : EstadoMatriz::from((string) $estado);
    }

    /** @return BelongsTo<Matriz, $this> */
    public function matriz(): BelongsTo
    {
        return $this->belongsTo(Matriz::class);
    }

    /** @return BelongsTo<Riesgo, $this> */
    public function riesgoOrigen(): BelongsTo
    {
        return $this->belongsTo(self::class, 'riesgo_origen_id');
    }

    /** @return BelongsToMany<FactorRiesgo, $this> */
    public function factores(): BelongsToMany
    {
        return $this->belongsToMany(FactorRiesgo::class, 'riesgo_factor');
    }

    /** @return BelongsTo<NivelRiesgo, $this> */
    public function nivelCalculado(): BelongsTo
    {
        return $this->belongsTo(NivelRiesgo::class, 'nivel_calculado_id');
    }

    /** @return BelongsTo<NivelRiesgo, $this> */
    public function nivelAjustado(): BelongsTo
    {
        return $this->belongsTo(NivelRiesgo::class, 'nivel_ajustado_id');
    }

    /** @return BelongsTo<Periodicidad, $this> */
    public function periodicidad(): BelongsTo
    {
        return $this->belongsTo(Periodicidad::class);
    }

    /** @return HasMany<Seguimiento, $this> */
    public function seguimientos(): HasMany
    {
        return $this->hasMany(Seguimiento::class);
    }

    /** Nivel que rige: el ajustado si existe; si no, el calculado. */
    public function nivelEfectivo(): ?NivelRiesgo
    {
        return $this->nivelAjustado ?? $this->nivelCalculado;
    }

    public function estaEvaluado(): bool
    {
        return $this->probabilidad !== null && $this->impacto !== null;
    }
}
