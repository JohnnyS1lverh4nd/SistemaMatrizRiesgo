<?php

use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\Escenario;

/*
 * Fase 10 (seguridad): límites de peticiones por usuario en la API.
 * General: 300 por minuto. Exportar e importar (costosas): 20 por minuto.
 */

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = Escenario::usuario('administrador');
    $proceso = Escenario::proceso(Escenario::departamento('Compras'), 'SP 4.3. Abastecimiento');
    $this->matriz = Escenario::edicion($proceso, 'Abril-Junio 2026', [['p' => 3, 'i' => 4]]);
    RateLimiter::clear('api');
});

test('every api route is rate limited per user', function () {
    $this->actingAs($this->admin)->getJson('/api/user')
        ->assertOk()
        ->assertHeader('X-RateLimit-Limit', '300');
});

test('expensive operations have a stricter limit', function () {
    $this->actingAs($this->admin);

    for ($i = 1; $i <= 20; $i++) {
        $this->get("/api/matrices/{$this->matriz->id}/exportar/excel")->assertOk();
    }

    $this->getJson("/api/matrices/{$this->matriz->id}/exportar/pdf")
        ->assertTooManyRequests()
        ->assertHeader('Retry-After');
});

test('the limits are counted per user, not shared', function () {
    $this->actingAs($this->admin);
    for ($i = 1; $i <= 20; $i++) {
        $this->get("/api/matrices/{$this->matriz->id}/exportar/excel");
    }

    $this->actingAs(Escenario::usuario('auditor'))
        ->get("/api/matrices/{$this->matriz->id}/exportar/excel")
        ->assertOk();
});
