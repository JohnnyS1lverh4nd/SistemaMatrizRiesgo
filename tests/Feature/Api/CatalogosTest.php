<?php

use App\Models\EscalaNivel;
use App\Models\FactorRiesgo;
use App\Models\MatrizCalorCelda;
use App\Models\NivelRiesgo;
use App\Models\Periodicidad;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

/*
 * Catálogos de la Fase 2: lectura para cualquier rol autenticado,
 * escritura solo para el Administrador.
 */

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::factory()->create(['role_id' => Role::where('slug', 'administrador')->value('id')]);
    $this->encargado = User::factory()->create(['role_id' => Role::where('slug', 'encargado-departamento')->value('id')]);
});

/**
 * [uri base, clase del modelo, cambio válido para update]
 */
dataset('catalogos', [
    'factores' => ['/api/factores-riesgo', FactorRiesgo::class, ['nombre' => 'Renombrado']],
    'periodicidades' => ['/api/periodicidades', Periodicidad::class, ['nombre' => 'Renombrada']],
    'niveles' => ['/api/niveles-riesgo', NivelRiesgo::class, ['nombre' => 'Renombrado']],
    'escalas' => ['/api/escalas-nivel', EscalaNivel::class, ['nombre' => 'Renombrada']],
]);

test('guests can not read catalogs', function (string $uri) {
    $this->getJson($uri)->assertUnauthorized();
})->with('catalogos');

test('any authenticated role can list a catalog', function (string $uri, string $modelo) {
    $this->actingAs($this->encargado)->getJson($uri)
        ->assertOk()
        ->assertJsonCount($modelo::count(), 'data');
})->with('catalogos');

test('show returns the requested record', function (string $uri, string $modelo) {
    $registro = $modelo::query()->orderByDesc('id')->first();

    $this->actingAs($this->encargado)->getJson("{$uri}/{$registro->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $registro->id)
        ->assertJsonPath('data.nombre', $registro->nombre);
})->with('catalogos');

test('show returns not found for a missing record', function (string $uri) {
    $this->actingAs($this->encargado)->getJson("{$uri}/99999")->assertNotFound();
})->with('catalogos');

test('admin update changes the requested record', function (string $uri, string $modelo, array $cambio) {
    $registro = $modelo::query()->orderByDesc('id')->first();

    $this->actingAs($this->admin)->putJson("{$uri}/{$registro->id}", $cambio)
        ->assertOk()
        ->assertJsonPath('data.id', $registro->id)
        ->assertJsonPath('data.nombre', $cambio['nombre']);

    expect($registro->fresh()->nombre)->toBe($cambio['nombre']);
})->with('catalogos');

test('non admin roles can not modify catalogs', function (string $uri, string $modelo, array $cambio) {
    $registro = $modelo::query()->first();

    $this->actingAs($this->encargado)->putJson("{$uri}/{$registro->id}", $cambio)->assertForbidden();

    expect($registro->fresh()->nombre)->not->toBe($cambio['nombre']);
})->with('catalogos');

// --- Sin borrado físico -------------------------------------------------------

test('catalog records can not be deleted, not even by the admin', function (string $uri, string $modelo) {
    $registro = $modelo::query()->first();

    $this->actingAs($this->admin)->deleteJson("{$uri}/{$registro->id}")->assertMethodNotAllowed();

    $this->assertModelExists($registro);
})->with('catalogos');

test('factors and periodicities are deactivated instead of deleted', function (string $uri, string $modelo) {
    $registro = $modelo::query()->first();

    $this->actingAs($this->admin)->putJson("{$uri}/{$registro->id}", ['activo' => false])
        ->assertOk()
        ->assertJsonPath('data.activo', false);

    // Sigue existiendo, pero sale de la lista de opciones activas.
    $this->actingAs($this->encargado)->getJson("{$uri}?activo=1")
        ->assertOk()
        ->assertJsonMissing(['id' => $registro->id]);
    $this->actingAs($this->encargado)->getJson("{$uri}?activo=0")
        ->assertJsonFragment(['id' => $registro->id]);
})->with([
    'factores' => ['/api/factores-riesgo', FactorRiesgo::class],
    'periodicidades' => ['/api/periodicidades', Periodicidad::class],
]);

test('factors and periodicities can still be created by the admin', function (string $uri, string $modelo) {
    $this->actingAs($this->admin)->postJson($uri, ['nombre' => 'Nuevo', 'slug' => 'nuevo'])
        ->assertCreated()
        ->assertJsonPath('data.activo', true);

    expect($modelo::where('slug', 'nuevo')->exists())->toBeTrue();
})->with([
    'factores' => ['/api/factores-riesgo', FactorRiesgo::class],
    'periodicidades' => ['/api/periodicidades', Periodicidad::class],
]);

// --- Niveles y escalas: estructura fija -----------------------------------------

test('risk levels and scales can not be created', function (string $uri, array $datos) {
    $this->actingAs($this->admin)->postJson($uri, $datos)->assertMethodNotAllowed();
})->with([
    'nivel' => ['/api/niveles-riesgo', ['nombre' => 'Extremo', 'slug' => 'extremo', 'color' => '#000000', 'orden' => 5]],
    'escala' => ['/api/escalas-nivel', ['tipo' => 'impacto', 'valor' => 5, 'nombre' => 'Catastrófico']],
]);

test('only name and color of a risk level are editable', function () {
    $nivel = NivelRiesgo::where('slug', 'alto')->firstOrFail();

    $this->actingAs($this->admin)->putJson("/api/niveles-riesgo/{$nivel->id}", [
        'nombre' => 'Alto (revisar)',
        'color' => '#AA3300',
        'slug' => 'otro-slug',
        'orden' => 9,
    ])->assertOk()
        ->assertJsonPath('data.nombre', 'Alto (revisar)')
        ->assertJsonPath('data.color', '#AA3300');

    $nivel->refresh();
    expect($nivel->slug)->toBe('alto')->and($nivel->orden)->toBe(3);
});

test('risk level color must be a hexadecimal color', function (string $color) {
    $nivel = NivelRiesgo::firstOrFail();

    $this->actingAs($this->admin)->putJson("/api/niveles-riesgo/{$nivel->id}", ['color' => $color])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('color');
})->with(['rojo', '#12345', '#GGGGGG', '123456']);

test('scale type and value can not be changed', function () {
    $escala = EscalaNivel::where(['tipo' => 'impacto', 'valor' => 4])->firstOrFail();

    $this->actingAs($this->admin)->putJson("/api/escalas-nivel/{$escala->id}", [
        'nombre' => 'Severo',
        'tipo' => 'probabilidad',
        'valor' => 9,
    ])->assertOk();

    $escala->refresh();
    expect($escala->nombre)->toBe('Severo')
        ->and($escala->tipo->value)->toBe('impacto')
        ->and($escala->valor)->toBe(4);
});

test('heat map cell update changes the requested cell', function () {
    $celda = MatrizCalorCelda::where(['probabilidad' => 4, 'impacto' => 4])->firstOrFail();
    $bajo = NivelRiesgo::where('slug', 'bajo')->firstOrFail();

    $this->actingAs($this->admin)->putJson("/api/matriz-calor-celdas/{$celda->id}", ['nivel_riesgo_id' => $bajo->id])
        ->assertOk()
        ->assertJsonPath('data.id', $celda->id);

    expect($celda->fresh()->nivel_riesgo_id)->toBe($bajo->id);
});

test('heat map always has the sixteen probability by impact cells', function () {
    expect(MatrizCalorCelda::count())->toBe(16);

    $this->actingAs($this->admin)->postJson('/api/matriz-calor-celdas', [])->assertMethodNotAllowed();
});
