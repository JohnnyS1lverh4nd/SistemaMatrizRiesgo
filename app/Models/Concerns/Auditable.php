<?php

namespace App\Models\Concerns;

use App\Services\Auditor;
use Illuminate\Support\Str;

/**
 * Audita automáticamente altas, cambios y eliminaciones del modelo: ningún
 * controlador o servicio futuro puede olvidarlo.
 *
 * Personalización por modelo:
 *  - nombreEntidadAuditoria(): nombre estable ('usuario' en lugar de 'user').
 *  - camposSensibles(): se registra que cambiaron, nunca su valor.
 *  - camposExcluidosDeAuditoria(): no se registran en absoluto.
 *  - eventoAuditoria(): nombre de negocio (publicado, anulado...).
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (self $modelo) {
            $nuevos = $modelo->valoresAuditables($modelo->getAttributes());
            $modelo->auditar($modelo->eventoAuditoria('creado', $nuevos), null, $nuevos);
        });

        static::updated(function (self $modelo) {
            $nuevos = $modelo->valoresAuditables($modelo->getChanges());

            if ($nuevos === []) {
                return;
            }

            // En 'updated' el original aún conserva los valores previos.
            $anteriores = $modelo->valoresAuditables(array_intersect_key($modelo->getRawOriginal(), $nuevos));
            $modelo->auditar($modelo->eventoAuditoria('actualizado', $nuevos), $anteriores, $nuevos);
        });

        static::deleted(function (self $modelo) {
            $modelo->auditar('eliminado', null, null);
        });
    }

    public function nombreEntidadAuditoria(): string
    {
        return Str::snake(class_basename($this));
    }

    /** @return list<string> */
    protected function camposSensibles(): array
    {
        return [];
    }

    /** @return list<string> */
    protected function camposExcluidosDeAuditoria(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    protected function eventoAuditoria(string $evento, array $cambios): string
    {
        return $evento;
    }

    /**
     * @param  array<string, mixed>  $valores
     * @return array<string, mixed>
     */
    private function valoresAuditables(array $valores): array
    {
        $excluidos = [...$this->camposExcluidosDeAuditoria(), 'created_at', 'updated_at'];
        $resultado = array_diff_key($valores, array_flip($excluidos));

        foreach ($this->camposSensibles() as $campo) {
            if (array_key_exists($campo, $resultado)) {
                $resultado[$campo] = '[oculto]';
            }
        }

        return $resultado;
    }

    /**
     * @param  array<string, mixed>|null  $antes
     * @param  array<string, mixed>|null  $despues
     */
    private function auditar(string $evento, ?array $antes, ?array $despues): void
    {
        Auditor::registrar($evento, $this->nombreEntidadAuditoria(), (int) $this->getKey(), $antes, $despues);
    }
}
