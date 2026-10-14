<?php

namespace App\Models;

use App\Enums\EstadoMatriz;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Una edición de la matriz de riesgos de un proceso.
 *
 * @property int $id
 * @property int $proceso_id
 * @property string $nombre
 * @property Carbon $fecha_revision
 * @property EstadoMatriz $estado
 * @property int|null $edicion_anterior_id
 * @property Carbon|null $publicada_at
 * @property Carbon|null $archivada_at
 */
class Matriz extends Model
{
    use SoftDeletes;

    protected $table = 'matrices';

    /**
     * estado, publicada_at y archivada_at no son asignables: solo cambian
     * con MatrizService (publicar/archivar).
     */
    protected $fillable = [
        'proceso_id',
        'nombre',
        'fecha_revision',
        'edicion_anterior_id',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'estado' => 'borrador',
    ];

    /**
     * Campos que pueden cambiar en una edición ya publicada (transición de estado).
     */
    private const CAMPOS_DE_TRANSICION = ['estado', 'publicada_at', 'archivada_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'estado' => EstadoMatriz::class,
            'fecha_revision' => 'date',
            'publicada_at' => 'datetime',
            'archivada_at' => 'datetime',
        ];
    }

    /**
     * Defensa en profundidad: aunque un flujo futuro olvide la Policy, una
     * edición vigente o archivada no puede modificarse ni eliminarse, y el
     * estado solo avanza borrador -> vigente -> archivada.
     */
    protected static function booted(): void
    {
        static::updating(function (Matriz $matriz) {
            $original = EstadoMatriz::from($matriz->getRawOriginal('estado'));

            if ($matriz->isDirty('estado') && ! $original->puedePasarA($matriz->estado)) {
                throw new LogicException("Transición de estado no permitida: {$original->value} -> {$matriz->estado->value}.");
            }

            $otrosCambios = array_diff(array_keys($matriz->getDirty()), self::CAMPOS_DE_TRANSICION);

            if ($original !== EstadoMatriz::Borrador && $otrosCambios !== []) {
                throw new LogicException("La edición {$matriz->id} está {$original->value} y no admite cambios.");
            }
        });

        static::deleting(function (Matriz $matriz) {
            if ($matriz->estado !== EstadoMatriz::Borrador) {
                throw new LogicException("Solo se pueden descartar borradores; la edición {$matriz->id} está {$matriz->estado->value}.");
            }
        });
    }

    /** @return BelongsTo<Proceso, $this> */
    public function proceso(): BelongsTo
    {
        return $this->belongsTo(Proceso::class);
    }

    /** @return BelongsTo<Matriz, $this> */
    public function edicionAnterior(): BelongsTo
    {
        return $this->belongsTo(self::class, 'edicion_anterior_id');
    }

    /** @return HasMany<Matriz, $this> */
    public function edicionesDerivadas(): HasMany
    {
        return $this->hasMany(self::class, 'edicion_anterior_id');
    }

    /** @return HasMany<Riesgo, $this> */
    public function riesgos(): HasMany
    {
        return $this->hasMany(Riesgo::class)->orderBy('orden');
    }

    public function esEditable(): bool
    {
        return $this->estado === EstadoMatriz::Borrador;
    }

    /**
     * Matrices de los departamentos que el usuario puede consultar.
     *
     * @param  Builder<Matriz>  $query
     */
    public function scopeVisiblesPara(Builder $query, User $user): void
    {
        $query->whereHas('proceso', fn (Builder $procesos) => $procesos->whereIn(
            'departamento_id',
            Departamento::visiblesPara($user)->select('departamentos.id'),
        ));
    }
}
