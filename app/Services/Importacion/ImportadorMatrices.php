<?php

namespace App\Services\Importacion;

use App\Models\FactorRiesgo;
use App\Models\Matriz;
use App\Models\NivelRiesgo;
use App\Models\Periodicidad;
use App\Models\Proceso;
use App\Models\Riesgo;
use App\Models\User;
use App\Services\Auditor;
use App\Services\CalculadoraNivelRiesgo;
use App\Services\MatrizService;
use App\Services\RiesgoService;
use App\Services\SeguimientoService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

/**
 * Importación de matrices desde los Excel institucionales (RF-7).
 *
 * analizar() lee el archivo y traduce cada fila a datos del dominio
 * (catálogos, nivel, fechas) sin escribir nada; devuelve un plan con los
 * errores y avisos. ejecutar() crea las ediciones a partir de un plan válido
 * con los servicios de siempre (mismas reglas, misma auditoría), en una
 * única transacción: se importa el archivo completo o nada.
 *
 * Reglas (docs/importacion.md):
 * - Cada hoja visible con el formato es una edición; se ordenan por fecha de
 *   revisión, la última queda vigente y las anteriores archivadas.
 * - Un riesgo con varias sub-filas se importa como un riesgo por sub-fila.
 * - Si el nivel del Excel no coincide con la matriz de calor, se guarda como
 *   ajuste manual justificado.
 * - La columna Seguimiento/Evidencias se registra como un seguimiento con la
 *   fecha de revisión de la edición.
 * - Solo se importa en procesos sin ediciones: no se mezcla con historial.
 */
class ImportadorMatrices
{
    private const MESES = [
        'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6, 'julio' => 7,
        'agosto' => 8, 'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12,
    ];

    private const MAX_TEXTO = 10000;

    /** @var array<string, int> clave => id, cargados una vez por análisis */
    private array $factores = [];

    /** @var array<string, int> */
    private array $periodicidades = [];

    /** @var Collection<string, NivelRiesgo> */
    private Collection $niveles;

    /** @var list<string> */
    private array $errores = [];

    /** @var list<string> */
    private array $avisos = [];

    public function __construct(
        private readonly LectorExcel $lector,
        private readonly CalculadoraNivelRiesgo $calculadora,
        private readonly MatrizService $matrices,
        private readonly RiesgoService $riesgos,
        private readonly SeguimientoService $seguimientos,
    ) {
        $this->niveles = collect();
    }

    public function analizar(string $ruta, Proceso $proceso, ?string $nombreArchivo = null): PlanImportacion
    {
        $this->errores = [];
        $this->avisos = [];
        $archivo = $nombreArchivo ?? basename($ruta);

        try {
            $hojas = $this->lector->leer($ruta);
        } catch (InvalidArgumentException $e) {
            return new PlanImportacion($archivo, [], [$e->getMessage()], []);
        }

        if ($hojas === []) {
            return new PlanImportacion($archivo, [], ['El archivo no contiene hojas con el formato de matriz de riesgos (RT-01-NI-ONA-156).'], []);
        }

        if (Matriz::withTrashed()->where('proceso_id', $proceso->id)->exists()) {
            $this->errores[] = "El proceso \"{$proceso->nombre}\" ya tiene ediciones: la importación solo carga procesos sin historial.";
        }

        $this->cargarCatalogos();

        $ediciones = array_map(fn (array $hoja) => $this->edicion($hoja, $proceso), $hojas);

        // Orden cronológico (estable: a igual fecha, el orden de las hojas).
        usort($ediciones, fn (array $a, array $b) => strcmp((string) $a['fecha_revision'], (string) $b['fecha_revision']));

        $nombres = array_column($ediciones, 'nombre');
        foreach (array_unique(array_diff_assoc($nombres, array_unique($nombres))) as $repetido) {
            $this->errores[] = "Hay varias hojas que darían la edición \"{$repetido}\".";
        }

        return new PlanImportacion($archivo, $this->enlazar($ediciones), $this->errores, $this->avisos);
    }

    /**
     * @return Collection<int, Matriz> ediciones creadas, en orden cronológico
     *
     * @throws LogicException si el plan tiene errores
     */
    public function ejecutar(PlanImportacion $plan, Proceso $proceso, User $usuario): Collection
    {
        if (! $plan->esValido()) {
            throw new LogicException('El plan de importación tiene errores y no puede ejecutarse.');
        }

        return DB::transaction(function () use ($plan, $proceso, $usuario) {
            $creadas = collect();
            /** @var list<Riesgo> $anteriores */
            $anteriores = [];

            foreach ($plan->ediciones as $edicion) {
                $matriz = Matriz::create([
                    'proceso_id' => $proceso->id,
                    'nombre' => $edicion['nombre'],
                    'fecha_revision' => $edicion['fecha_revision'],
                    'edicion_anterior_id' => $creadas->last()?->id,
                ]);

                $actuales = [];
                foreach ($edicion['riesgos'] as $riesgo) {
                    $origen = $riesgo['origen'] !== null ? $anteriores[$riesgo['origen']] : null;
                    $actuales[] = $this->riesgos->crear($matriz, $riesgo['datos'], $origen);
                }

                // Publicar archiva la edición vigente anterior (la recién importada).
                $this->matrices->publicar($matriz);

                foreach ($edicion['riesgos'] as $indice => $riesgo) {
                    if ($riesgo['seguimiento'] !== null) {
                        $this->seguimientos->registrar($actuales[$indice], $usuario, [
                            'fecha' => (string) $edicion['fecha_revision'],
                            'descripcion' => config('importacion.prefijo_seguimiento').$riesgo['seguimiento'],
                        ]);
                    }
                }

                Auditor::registrar('importado', 'matriz', $matriz->id, descripcion: "{$plan->archivo}, hoja \"{$edicion['hoja']}\"", usuario: $usuario);

                $creadas->push($matriz);
                $anteriores = $actuales;
            }

            return $creadas->map(fn (Matriz $m) => $m->refresh());
        });
    }

    /**
     * @param  array{nombre: string, encabezado: array{direccion: ?string, proceso: ?string, fecha_revision: ?string}, filas: list<array<string, mixed>>}  $hoja
     * @return array{hoja: string, nombre: string, fecha_revision: string|null, riesgos: list<array{fila: int, datos: array<string, mixed>, origen: null, seguimiento: string|null}>}
     */
    private function edicion(array $hoja, Proceso $proceso): array
    {
        $prefijo = "Hoja \"{$hoja['nombre']}\"";
        $fecha = $this->fecha($hoja['encabezado']['fecha_revision']);

        if ($fecha === null) {
            $this->errores[] = $hoja['encabezado']['fecha_revision'] === null
                ? "{$prefijo}: falta la fecha de revisión."
                : "{$prefijo}: no se reconoce la fecha de revisión \"{$hoja['encabezado']['fecha_revision']}\".";
        }

        $procesoExcel = $hoja['encabezado']['proceso'];
        if ($procesoExcel !== null && $this->clave($procesoExcel) !== $this->clave($proceso->nombre)) {
            $this->avisos[] = "{$prefijo}: el encabezado indica el proceso \"{$procesoExcel}\"; se importa en \"{$proceso->nombre}\".";
        }

        if ($hoja['filas'] === []) {
            $this->errores[] = "{$prefijo}: no tiene riesgos.";
        }

        return [
            'hoja' => $hoja['nombre'],
            'nombre' => $this->nombreEdicion($hoja['nombre'], $fecha),
            'fecha_revision' => $fecha?->toDateString(),
            'riesgos' => array_map(fn (array $fila) => $this->riesgo($fila, "{$prefijo}, fila {$fila['fila']}"), $hoja['filas']),
        ];
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array{fila: int, datos: array<string, mixed>, origen: null, seguimiento: string|null}
     */
    private function riesgo(array $fila, string $lugar): array
    {
        if ($fila['descripcion'] === null) {
            $this->errores[] = "{$lugar}: falta la descripción del riesgo.";
        }

        if ($fila['actividad'] === null) {
            $this->errores[] = "{$lugar}: falta la actividad.";
        }

        foreach (LectorExcel::COLUMNAS as $campo) {
            if (mb_strlen((string) $fila[$campo]) > self::MAX_TEXTO) {
                $this->errores[] = "{$lugar}: el campo {$campo} supera ".self::MAX_TEXTO.' caracteres.';
            }
        }

        [$probabilidad, $impacto] = $this->evaluacion($fila, $lugar);
        [$ajusteId, $justificacion] = $this->ajusteDeNivel($fila['nivel'], $probabilidad, $impacto, $lugar);

        return [
            'fila' => $fila['fila'],
            'datos' => [
                'actividad' => $fila['actividad'],
                'descripcion' => $fila['descripcion'],
                'causas' => $fila['causas'],
                'control_actual' => $fila['control_actual'],
                'probabilidad' => $probabilidad,
                'impacto' => $impacto,
                'sintoma' => $fila['sintoma'],
                'tratamiento' => $fila['tratamiento'],
                'responsable' => $fila['responsable'],
                'periodicidad_id' => $this->periodicidad($fila['periodicidad'], $lugar),
                'factor_ids' => $this->factores($fila['factor'], $lugar),
                'nivel_ajustado_id' => $ajusteId,
                'justificacion_ajuste' => $justificacion,
            ],
            'origen' => null,
            'seguimiento' => $fila['seguimiento'],
        ];
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array{0: int|null, 1: int|null}
     */
    private function evaluacion(array $fila, string $lugar): array
    {
        if ($fila['probabilidad'] === null || $fila['impacto'] === null) {
            $this->errores[] = "{$lugar}: falta la probabilidad o el impacto.";

            return [null, null];
        }

        $valores = [];
        foreach (['probabilidad', 'impacto'] as $campo) {
            $valor = filter_var($fila[$campo], FILTER_VALIDATE_INT, ['options' => [
                'min_range' => CalculadoraNivelRiesgo::MINIMO,
                'max_range' => CalculadoraNivelRiesgo::MAXIMO,
            ]]);
            $valores[] = $valor === false ? null : $valor;
        }

        if (in_array(null, $valores, true)) {
            $this->errores[] = "{$lugar}: la probabilidad y el impacto deben ser números del ".CalculadoraNivelRiesgo::MINIMO.' al '.CalculadoraNivelRiesgo::MAXIMO.'.';

            return [null, null];
        }

        return $valores;
    }

    /**
     * El nivel del Excel se elegía a mano; si no coincide con la matriz de
     * calor se conserva como ajuste manual justificado (RF-3).
     *
     * @return array{0: int|null, 1: string|null}
     */
    private function ajusteDeNivel(?string $nivelExcel, ?int $probabilidad, ?int $impacto, string $lugar): array
    {
        if ($probabilidad === null || $impacto === null) {
            return [null, null];
        }

        $calculado = $this->calculadora->calcular($probabilidad, $impacto);

        if ($nivelExcel === null) {
            $this->avisos[] = "{$lugar}: el Excel no indica el nivel; se usa el calculado ({$calculado?->nombre}).";

            return [null, null];
        }

        // Un Excel exportado por el sistema añade "Ajustado: <justificación>"
        // en otra línea de la misma celda: el nivel es la primera.
        $lineas = explode("\n", $nivelExcel);
        $nombreNivel = trim($lineas[0]);
        $justificacionExcel = null;
        foreach (array_slice($lineas, 1) as $linea) {
            if (preg_match('/^\s*Ajustado:\s*(.+)$/u', $linea, $m)) {
                $justificacionExcel = trim($m[1]);
            }
        }

        $nivel = $this->niveles->get($this->clave($nombreNivel));

        if ($nivel === null) {
            $this->errores[] = "{$lugar}: el nivel \"{$nombreNivel}\" no existe en el catálogo.";

            return [null, null];
        }

        if ($calculado === null || $nivel->id === $calculado->id) {
            return [null, null];
        }

        $this->avisos[] = "{$lugar}: el Excel indica {$nivel->nombre} y la matriz de calor calcula {$calculado->nombre}; se importa como ajuste manual.";

        return [$nivel->id, $justificacionExcel ?? (string) config('importacion.justificacion_nivel')];
    }

    /**
     * "Pnal / Tec" -> [Personal, Tecnológico]
     *
     * @return list<int>
     */
    private function factores(?string $texto, string $lugar): array
    {
        $ids = [];

        foreach (preg_split('#\s*/\s*#', (string) $texto, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $nombre) {
            $clave = $this->clave($nombre);
            $id = $this->factores[config("importacion.alias_factores.{$clave}") ?? $clave] ?? null;

            if ($id === null) {
                $this->errores[] = "{$lugar}: el factor \"{$nombre}\" no existe en el catálogo.";
            } elseif (! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private function periodicidad(?string $texto, string $lugar): ?int
    {
        if ($texto === null) {
            return null;
        }

        $clave = $this->clave($texto);
        $id = $this->periodicidades[config("importacion.alias_periodicidades.{$clave}") ?? $clave] ?? null;

        if ($id === null) {
            $this->errores[] = "{$lugar}: la periodicidad \"{$texto}\" no existe en el catálogo.";
        }

        return $id;
    }

    /**
     * "JUNIO 2026" -> 30/06/2026 (sin día: último del mes); "30 Abril 2025";
     * "15 de marzo de 2026"; "31/03/2026".
     */
    private function fecha(?string $texto): ?CarbonImmutable
    {
        $clave = $this->clave($texto);
        $meses = implode('|', array_keys(self::MESES));

        if (preg_match("/^(?:(\\d{1,2})\\s+(?:de\\s+)?)?({$meses})\\s+(?:del?\\s+)?(\\d{4})$/", $clave, $m)) {
            [$dia, $mes, $anio] = [$m[1] === '' ? null : (int) $m[1], self::MESES[$m[2]], (int) $m[3]];
        } elseif (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $clave, $m)) {
            [$dia, $mes, $anio] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return null;
        }

        if ($dia === null) {
            return CarbonImmutable::create($anio, $mes, 1)?->endOfMonth()->startOfDay();
        }

        return checkdate($mes, $dia, $anio) ? CarbonImmutable::create($anio, $mes, $dia) : null;
    }

    /**
     * "Matriz de Riesgos_Edición 3" -> "Edición 3"; "ENERO-MARZO 2026" ->
     * "Enero-Marzo 2026"; una hoja genérica ("Hoja1") toma el mes de revisión.
     */
    private function nombreEdicion(string $hoja, ?CarbonImmutable $fecha): string
    {
        if (preg_match('/edici[oó]n\s*(\d+)/iu', $hoja, $m)) {
            return "Edición {$m[1]}";
        }

        if (preg_match('/^(hoja|sheet)\s*\d*$/i', trim($hoja)) && $fecha !== null) {
            return Str::ucfirst($fecha->settings(['locale' => 'es'])->translatedFormat('F Y'));
        }

        return mb_convert_case(trim($hoja), MB_CASE_TITLE);
    }

    /**
     * Enlaza cada riesgo con el equivalente de la edición anterior: misma
     * descripción y, si se repite (sub-filas), misma posición entre ellas.
     *
     * @param  list<array{hoja: string, nombre: string, fecha_revision: string|null, riesgos: list<array{fila: int, datos: array<string, mixed>, origen: null, seguimiento: string|null}>}>  $ediciones
     * @return list<array{hoja: string, nombre: string, fecha_revision: string|null, riesgos: list<array{fila: int, datos: array<string, mixed>, origen: int|null, seguimiento: string|null}>}>
     */
    private function enlazar(array $ediciones): array
    {
        $anterior = [];
        $enlazadas = [];

        foreach ($ediciones as $edicion) {
            $actual = [];
            $riesgos = [];

            foreach ($edicion['riesgos'] as $posicion => $riesgo) {
                $base = $this->clave($riesgo['datos']['descripcion']);
                $n = 0;
                while (isset($actual["{$base}#{$n}"])) {
                    $n++;
                }
                $actual["{$base}#{$n}"] = $posicion;
                $riesgos[] = [...$riesgo, 'origen' => $anterior["{$base}#{$n}"] ?? null];
            }

            $enlazadas[] = [...$edicion, 'riesgos' => $riesgos];
            $anterior = $actual;
        }

        return $enlazadas;
    }

    private function cargarCatalogos(): void
    {
        // Factores y periodicidades: solo los activos, como en el formulario.
        $this->factores = [];
        foreach (FactorRiesgo::where('activo', true)->get() as $factor) {
            $this->factores[$factor->slug] = $factor->id;
            $this->factores[$this->clave($factor->nombre)] = $factor->id;
        }

        $this->periodicidades = [];
        foreach (Periodicidad::where('activo', true)->get() as $periodicidad) {
            $this->periodicidades[$periodicidad->slug] = $periodicidad->id;
            $this->periodicidades[$this->clave($periodicidad->nombre)] = $periodicidad->id;
        }

        $this->niveles = NivelRiesgo::all()->keyBy(fn (NivelRiesgo $nivel) => $this->clave($nivel->nombre));
    }

    /** Minúsculas, sin acentos, sin punto final ni espacios sobrantes. */
    private function clave(?string $texto): string
    {
        return rtrim(Str::squish(Str::lower(Str::ascii((string) $texto))), '.');
    }
}
