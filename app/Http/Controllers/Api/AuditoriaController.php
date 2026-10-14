<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditoriaResource;
use App\Models\Auditoria;
use App\Models\Matriz;
use App\Models\Riesgo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Consulta del registro de auditoría (solo lectura; no hay escritura por API).
 *   GET /api/auditoria                    Administrador y Auditor, con filtros
 *   GET /api/riesgos/{riesgo}/historial   quien puede ver el riesgo
 *   GET /api/matrices/{matriz}/historial  quien puede ver la edición
 */
class AuditoriaController extends Controller
{
    private const POR_PAGINA = 50;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Auditoria::class);

        $filtros = $request->validate([
            'entidad' => ['sometimes', 'string', 'max:60'],
            'entidad_id' => ['sometimes', 'integer'],
            'evento' => ['sometimes', 'string', 'max:40'],
            'usuario_id' => ['sometimes', 'integer'],
            'desde' => ['sometimes', 'date_format:Y-m-d'],
            'hasta' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ]);

        $registros = Auditoria::with('usuario')
            ->when($filtros['entidad'] ?? null, fn ($q, $v) => $q->where('entidad', $v))
            ->when($filtros['entidad_id'] ?? null, fn ($q, $v) => $q->where('entidad_id', $v))
            ->when($filtros['evento'] ?? null, fn ($q, $v) => $q->where('evento', $v))
            ->when($filtros['usuario_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filtros['desde'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v.' 00:00:00'))
            ->when($filtros['hasta'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', $v.' 23:59:59'))
            ->orderByDesc('id')
            ->paginate(self::POR_PAGINA);

        return AuditoriaResource::collection($registros);
    }

    public function riesgo(Riesgo $riesgo): AnonymousResourceCollection
    {
        $this->authorize('view', $riesgo);

        return $this->historial('riesgo', $riesgo->id);
    }

    public function matriz(Matriz $matriz): AnonymousResourceCollection
    {
        $this->authorize('view', $matriz);

        return $this->historial('matriz', $matriz->id);
    }

    private function historial(string $entidad, int $id): AnonymousResourceCollection
    {
        return AuditoriaResource::collection(
            Auditoria::with('usuario')
                ->where(['entidad' => $entidad, 'entidad_id' => $id])
                ->orderByDesc('id')
                ->get()
        );
    }
}
