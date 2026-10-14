<?php

namespace Tests\Support;

use App\Enums\EstadoMatriz;
use App\Enums\EstadoTratamiento;
use App\Models\Departamento;
use App\Models\FactorRiesgo;
use App\Models\Matriz;
use App\Models\NivelRiesgo;
use App\Models\Periodicidad;
use App\Models\Proceso;
use App\Models\Riesgo;
use App\Models\Role;
use App\Models\User;
use App\Services\MatrizService;
use App\Services\RiesgoService;

/**
 * Construye escenarios de prueba con los servicios reales (nivel calculado
 * por la CalculadoraNivelRiesgo, clonado con MatrizService...).
 */
class Escenario
{
    public static function departamento(string $nombre, ?string $direccion = null): Departamento
    {
        return Departamento::create(['nombre' => $nombre, 'slug' => str($nombre)->slug()->toString(), 'direccion' => $direccion]);
    }

    public static function proceso(Departamento $departamento, string $nombre): Proceso
    {
        return Proceso::create(['departamento_id' => $departamento->id, 'nombre' => $nombre, 'slug' => str($nombre)->slug()->toString()]);
    }

    public static function usuario(string $rol, Departamento ...$departamentos): User
    {
        $user = User::factory()->create(['role_id' => Role::where('slug', $rol)->value('id')]);
        $user->departamentos()->attach(collect($departamentos)->pluck('id'));

        return $user;
    }

    /**
     * Edición con riesgos. Cada riesgo: ['p' => 3, 'i' => 4, 'descripcion' => ...,
     * 'factores' => ['administrativo'], 'periodicidad' => 'trimestral',
     * 'responsable' => ..., 'estado_tratamiento' => 'pendiente', 'ajuste' => 'moderado'].
     *
     * @param  array<int, array<string, mixed>>  $riesgos
     */
    public static function edicion(
        Proceso $proceso,
        string $nombre,
        array $riesgos = [],
        EstadoMatriz $estado = EstadoMatriz::Vigente,
        string $fecha = '2026-03-31',
    ): Matriz {
        $matriz = Matriz::create(['proceso_id' => $proceso->id, 'nombre' => $nombre, 'fecha_revision' => $fecha]);

        foreach ($riesgos as $indice => $datos) {
            app(RiesgoService::class)->crear($matriz, self::datosRiesgo($datos, $indice));
        }

        return self::llevarA($matriz, $estado);
    }

    /**
     * Nueva edición a partir de otra; $cambios[orden] modifica los riesgos
     * copiados (null = eliminarlo) y $nuevos agrega riesgos.
     *
     * @param  array<int, array<string, mixed>|null>  $cambios
     * @param  array<int, array<string, mixed>>  $nuevos
     */
    public static function clonar(
        Matriz $origen,
        string $nombre,
        array $cambios = [],
        array $nuevos = [],
        EstadoMatriz $estado = EstadoMatriz::Vigente,
        string $fecha = '2026-06-30',
    ): Matriz {
        $copia = app(MatrizService::class)->clonar($origen, ['nombre' => $nombre, 'fecha_revision' => $fecha]);

        foreach ($cambios as $orden => $datos) {
            $riesgo = Riesgo::where(['matriz_id' => $copia->id, 'orden' => $orden])->firstOrFail();
            if ($datos === null) {
                $riesgo->delete();

                continue;
            }
            app(RiesgoService::class)->actualizar($riesgo, self::datosRiesgo($datos, 0, parcial: true));
        }

        foreach ($nuevos as $indice => $datos) {
            app(RiesgoService::class)->crear($copia, self::datosRiesgo($datos, $indice));
        }

        // Publicar archiva la vigente anterior del proceso, como en la aplicación.
        if ($estado !== EstadoMatriz::Borrador) {
            app(MatrizService::class)->publicar($copia);
        }

        return self::llevarA($copia->fresh(), $estado);
    }

    private static function llevarA(Matriz $matriz, EstadoMatriz $estado): Matriz
    {
        foreach ([EstadoMatriz::Vigente, EstadoMatriz::Archivada] as $paso) {
            if ($estado === EstadoMatriz::Borrador || $matriz->estado === $estado) {
                break;
            }
            $matriz->forceFill([
                'estado' => $paso,
                $paso === EstadoMatriz::Vigente ? 'publicada_at' : 'archivada_at' => now(),
            ])->save();
        }

        return $matriz->fresh();
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private static function datosRiesgo(array $datos, int $indice, bool $parcial = false): array
    {
        $resultado = [];

        if (! $parcial || isset($datos['descripcion'])) {
            $resultado['descripcion'] = $datos['descripcion'] ?? 'Riesgo '.($indice + 1);
            $resultado['actividad'] = $datos['actividad'] ?? 'Actividad '.($indice + 1);
        }
        if (array_key_exists('p', $datos)) {
            $resultado['probabilidad'] = $datos['p'];
            $resultado['impacto'] = $datos['i'];
        }
        if (isset($datos['factores'])) {
            $resultado['factor_ids'] = FactorRiesgo::whereIn('slug', $datos['factores'])->pluck('id')->all();
        }
        if (isset($datos['periodicidad'])) {
            $resultado['periodicidad_id'] = Periodicidad::where('slug', $datos['periodicidad'])->value('id');
        }
        if (isset($datos['responsable'])) {
            $resultado['responsable'] = $datos['responsable'];
        }
        if (isset($datos['estado_tratamiento'])) {
            $resultado['estado_tratamiento'] = EstadoTratamiento::from($datos['estado_tratamiento'])->value;
        }
        if (isset($datos['ajuste'])) {
            $resultado['nivel_ajustado_id'] = NivelRiesgo::where('slug', $datos['ajuste'])->value('id');
            $resultado['justificacion_ajuste'] = 'Ajuste de prueba';
        }

        return $resultado;
    }
}
