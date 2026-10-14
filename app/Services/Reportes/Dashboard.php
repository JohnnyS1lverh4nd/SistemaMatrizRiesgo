<?php

namespace App\Services\Reportes;

use App\Enums\EstadoMatriz;
use App\Enums\EstadoTratamiento;
use App\Http\Resources\NivelRiesgoResource;
use App\Models\Departamento;
use App\Models\MatrizCalorCelda;
use App\Models\NivelRiesgo;
use App\Models\Riesgo;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Indicadores del dashboard (RF-6), siempre sobre ediciones vigentes y en el
 * alcance del usuario. Se agregan en PHP sobre una sola consulta: el volumen
 * es pequeño y así el cálculo es idéntico en cualquier motor de base de datos.
 */
class Dashboard
{
    public function __construct(private readonly ConsultaRiesgos $consulta) {}

    /**
     * @param  array{departamento_id?: int|string}  $filtros
     * @return array<string, mixed>
     */
    public function para(User $user, array $filtros = []): array
    {
        /** @var Collection<int, Riesgo> $riesgos */
        $riesgos = EstadoSeguimiento::preparar($this->consulta->para($user, $filtros))
            ->with('matriz.proceso')
            ->get();

        $niveles = NivelRiesgo::orderBy('orden')->get();
        $prioritarios = $niveles->whereIn('slug', ['alto', 'critico'])->pluck('id');
        $nivelDe = fn (Riesgo $r) => $r->nivel_ajustado_id ?? $r->nivel_calculado_id;

        return [
            'total_riesgos' => $riesgos->count(),
            'por_nivel' => $niveles->map(fn (NivelRiesgo $nivel) => [
                'nivel' => new NivelRiesgoResource($nivel),
                'total' => $riesgos->filter(fn (Riesgo $r) => $nivelDe($r) === $nivel->id)->count(),
            ])->values(),
            'mapa_calor' => $this->mapaCalor($riesgos),
            'por_estado_tratamiento' => collect(EstadoTratamiento::cases())->mapWithKeys(fn (EstadoTratamiento $estado) => [
                $estado->value => $riesgos->filter(fn (Riesgo $r) => $r->estado_tratamiento === $estado)->count(),
            ]),
            'prioritarios_pendientes' => $riesgos->filter(fn (Riesgo $r) => $prioritarios->contains($nivelDe($r))
                && $r->estado_tratamiento !== EstadoTratamiento::Cumplido)->count(),
            'seguimientos_atrasados' => $riesgos->filter(fn (Riesgo $r) => EstadoSeguimiento::de($r)['atrasado'])->count(),
            'por_departamento' => $this->porDepartamento($user, $filtros, $riesgos, $niveles, $nivelDe),
        ];
    }

    /**
     * Número de riesgos en cada celda Probabilidad × Impacto (16 celdas).
     *
     * @param  Collection<int, Riesgo>  $riesgos
     * @return array<int, array<string, mixed>>
     */
    private function mapaCalor(Collection $riesgos): array
    {
        return MatrizCalorCelda::with('nivelRiesgo')
            ->orderBy('probabilidad')
            ->orderBy('impacto')
            ->get()
            ->map(fn (MatrizCalorCelda $celda) => [
                'probabilidad' => $celda->probabilidad,
                'impacto' => $celda->impacto,
                'nivel' => new NivelRiesgoResource($celda->nivelRiesgo),
                'total' => $riesgos->filter(fn (Riesgo $r) => $r->probabilidad === $celda->probabilidad && $r->impacto === $celda->impacto)->count(),
            ])
            ->all();
    }

    /**
     * @param  array{departamento_id?: int|string}  $filtros
     * @param  Collection<int, Riesgo>  $riesgos
     * @param  Collection<int, NivelRiesgo>  $niveles
     * @return array<int, array<string, mixed>>
     */
    private function porDepartamento(User $user, array $filtros, Collection $riesgos, Collection $niveles, callable $nivelDe): array
    {
        return Departamento::visiblesPara($user)
            ->where('activo', true)
            ->when($filtros['departamento_id'] ?? null, fn ($q, $v) => $q->whereKey($v))
            ->with(['procesos' => fn ($q) => $q->where('activo', true)->withCount(['matrices as vigentes' => fn ($m) => $m->where('estado', EstadoMatriz::Vigente)])])
            ->orderBy('nombre')
            ->get()
            ->map(function (Departamento $departamento) use ($riesgos, $niveles, $nivelDe) {
                $suyos = $riesgos->filter(fn (Riesgo $r) => $r->matriz->proceso->departamento_id === $departamento->id);
                $conVigente = $departamento->procesos->filter(fn ($p) => $p->getAttribute('vigentes') > 0)->count();

                return [
                    'departamento' => [
                        'id' => $departamento->id,
                        'nombre' => $departamento->nombre,
                        'direccion' => $departamento->direccion,
                    ],
                    'total' => $suyos->count(),
                    'por_nivel' => $niveles->mapWithKeys(fn (NivelRiesgo $nivel) => [
                        $nivel->slug => $suyos->filter(fn (Riesgo $r) => $nivelDe($r) === $nivel->id)->count(),
                    ]),
                    'procesos_con_vigente' => $conVigente,
                    'procesos_sin_vigente' => $departamento->procesos->count() - $conVigente,
                ];
            })
            ->values()
            ->all();
    }
}
