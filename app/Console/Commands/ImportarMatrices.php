<?php

namespace App\Console\Commands;

use App\Models\Matriz;
use App\Models\Proceso;
use App\Models\User;
use App\Services\Importacion\ImportadorMatrices;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Carga inicial de matrices desde los Excel institucionales (RF-7).
 *
 *   php artisan matrices:importar archivo.xlsx --proceso=3 --usuario=admin@infotep.gob.do
 *   php artisan matrices:importar archivo.xlsx --proceso=3 --usuario=admin@infotep.gob.do --confirmar
 *
 * Sin --confirmar solo muestra el plan (vista previa). Todo lo creado queda
 * en la auditoría a nombre de --usuario, que debe ser un Administrador.
 */
class ImportarMatrices extends Command
{
    protected $signature = 'matrices:importar
        {archivo : Ruta del Excel (.xlsx)}
        {--proceso= : ID del proceso que recibe las ediciones}
        {--usuario= : Correo del Administrador que realiza la importación}
        {--confirmar : Importar de verdad (sin esta opción solo se muestra el plan)}';

    protected $description = 'Importa las ediciones de una matriz de riesgos desde un Excel institucional (RT-01-NI-ONA-156)';

    public function handle(ImportadorMatrices $importador): int
    {
        $proceso = $this->proceso();
        $usuario = $proceso !== null ? $this->usuario($proceso) : null;

        if ($proceso === null || $usuario === null) {
            return self::FAILURE;
        }

        // La auditoría toma el usuario autenticado: todo queda a su nombre.
        Auth::setUser($usuario);

        $plan = $importador->analizar((string) $this->argument('archivo'), $proceso);
        $resumen = $plan->resumen();

        $this->info("Archivo: {$resumen['archivo']} → proceso \"{$proceso->nombre}\" ({$proceso->departamento->nombre})");
        $this->table(
            ['Hoja', 'Edición', 'Fecha de revisión', 'Riesgos', 'Seguimientos', 'Ajustes de nivel'],
            array_map(fn (array $e) => [$e['hoja'], $e['nombre'], $e['fecha_revision'] ?? '—', $e['riesgos'], $e['seguimientos'], $e['ajustes']], $resumen['ediciones']),
        );

        foreach ($resumen['avisos'] as $aviso) {
            $this->warn("Aviso: {$aviso}");
        }

        if (! $plan->esValido()) {
            foreach ($resumen['errores'] as $error) {
                $this->error($error);
            }
            $this->error('No se ha importado nada. Corrija el Excel o los catálogos y vuelva a intentarlo.');

            return self::FAILURE;
        }

        if (! $this->option('confirmar')) {
            $this->info('Vista previa: no se ha importado nada. Repita el comando con --confirmar para importar.');

            return self::SUCCESS;
        }

        $ediciones = $importador->ejecutar($plan, $proceso, $usuario);
        $this->info("Importadas {$ediciones->count()} edición(es). Vigente: \"{$ediciones->last()?->nombre}\" (id {$ediciones->last()?->id}).");

        return self::SUCCESS;
    }

    private function proceso(): ?Proceso
    {
        $id = $this->option('proceso');

        if ($id === null || $id === '') {
            $this->error('Indique el proceso con --proceso=ID.');

            return null;
        }

        $proceso = Proceso::with('departamento')->find($id);

        if ($proceso === null) {
            $this->error("No existe el proceso {$id}.");
        }

        return $proceso;
    }

    private function usuario(Proceso $proceso): ?User
    {
        $correo = (string) $this->option('usuario');
        $usuario = User::with('role')->where('email', $correo)->first();

        if ($usuario === null) {
            $this->error("No existe el usuario \"{$correo}\". Indíquelo con --usuario=correo.");

            return null;
        }

        if (! $usuario->activo || ! $usuario->can('importar', [Matriz::class, $proceso])) {
            $this->error('Solo un Administrador activo puede importar matrices.');

            return null;
        }

        return $usuario;
    }
}
