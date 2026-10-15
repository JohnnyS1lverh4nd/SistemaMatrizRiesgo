<?php

use Illuminate\Support\Facades\Schema;

/*
 * Fase 10: en producción la caché, la cola y las sesiones usan la base de
 * datos (docker/produccion.env). Las pruebas usan array/sync y no las
 * necesitan, así que este test comprueba que una instalación nueva las crea.
 */

test('a fresh install creates the cache, queue and session tables', function (string $tabla) {
    expect(Schema::hasTable($tabla))->toBeTrue();
})->with(['cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions']);
