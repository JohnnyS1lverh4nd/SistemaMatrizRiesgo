<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Archivo adjunto a un seguimiento. Se guarda en el disco privado ('local')
 * y solo se descarga a través de la API, con autorización.
 *
 * @property int $id
 * @property int $seguimiento_id
 * @property string $nombre_original
 * @property string $ruta
 * @property string $mime
 * @property int $tamano
 * @property string $sha256
 */
class Evidencia extends Model
{
    use Auditable;

    /** @var list<string> */
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'tamano' => 'integer',
        ];
    }

    /** Las evidencias forman parte del histórico: no se modifican ni se borran. */
    protected static function booted(): void
    {
        static::updating(fn (Evidencia $evidencia) => throw new LogicException("La evidencia {$evidencia->id} es histórica y no se modifica."));
        static::deleting(fn (Evidencia $evidencia) => throw new LogicException("La evidencia {$evidencia->id} es histórica y no se elimina."));
    }

    /** @return BelongsTo<Seguimiento, $this> */
    public function seguimiento(): BelongsTo
    {
        return $this->belongsTo(Seguimiento::class);
    }
}
