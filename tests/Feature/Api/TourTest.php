<?php

use App\Models\Auditoria;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;

/*
 * Recorrido guiado (nov. 2026). El estado se guarda por usuario en el
 * servidor, para que se conserve entre navegadores y dispositivos:
 *   null        nunca lo inició ni lo omitió -> se le invita al entrar
 *   completado  llegó al final
 *   omitido     lo saltó o lo cerró antes de terminar
 * Repetirlo no necesita cambiar el estado: se inicia desde Ayuda.
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->usuario = User::factory()->create(['role_id' => Role::where('slug', 'encargado-departamento')->value('id')]);
});

test('a new user has never seen the tour', function () {
    $this->actingAs($this->usuario)->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('data.tour.estado', null)
        ->assertJsonPath('data.tour.actualizado_at', null);
});

test('the tour can be marked as completed or skipped', function (string $estado) {
    $this->travelTo(now()->setDate(2026, 11, 12)->setTime(10, 0));

    $this->actingAs($this->usuario)->putJson('/api/perfil/tour', ['estado' => $estado])
        ->assertOk()
        ->assertJsonPath('data.tour.estado', $estado)
        ->assertJsonPath('data.tour.actualizado_at', now()->toIso8601String());

    // Otro dispositivo: el estado viene del servidor, no del navegador.
    $this->actingAs($this->usuario->fresh())->getJson('/api/user')
        ->assertJsonPath('data.tour.estado', $estado);
})->with(['completado', 'omitido']);

test('a repeated tour can change the state again', function () {
    $this->actingAs($this->usuario)->putJson('/api/perfil/tour', ['estado' => 'omitido'])->assertOk();
    $this->actingAs($this->usuario)->putJson('/api/perfil/tour', ['estado' => 'completado'])
        ->assertOk()
        ->assertJsonPath('data.tour.estado', 'completado');
});

test('only valid states are accepted', function (mixed $estado) {
    $this->actingAs($this->usuario)->putJson('/api/perfil/tour', ['estado' => $estado])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('estado');

    expect($this->usuario->fresh()->tour_estado)->toBeNull();
})->with([[null], [''], ['pendiente'], ['visto'], [['completado']]]);

test('the tour state only changes for the authenticated user', function () {
    $otro = User::factory()->create();

    $this->actingAs($this->usuario)->putJson('/api/perfil/tour', ['estado' => 'completado', 'user_id' => $otro->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('user_id');

    $this->actingAs($this->usuario)->putJson('/api/perfil/tour', ['estado' => 'completado'])->assertOk();

    expect($otro->fresh()->tour_estado)->toBeNull();
});

test('guests and deactivated users can not change the tour state', function () {
    $this->putJson('/api/perfil/tour', ['estado' => 'completado'])->assertUnauthorized();

    $this->usuario->update(['activo' => false]);
    $this->actingAs($this->usuario)->putJson('/api/perfil/tour', ['estado' => 'completado'])->assertUnauthorized();
});

test('the tour state is a personal preference, not an audited change', function () {
    $antes = Auditoria::count();

    $this->actingAs($this->usuario)->putJson('/api/perfil/tour', ['estado' => 'completado'])->assertOk();

    expect(Auditoria::count())->toBe($antes);
});

test('the tour state of other users is not exposed in the user administration', function () {
    $admin = User::factory()->create(['role_id' => Role::where('slug', 'administrador')->value('id')]);
    $this->usuario->forceFill(['tour_estado' => 'completado'])->save();

    $this->actingAs($admin)->getJson("/api/users/{$this->usuario->id}")
        ->assertOk()
        ->assertJsonMissingPath('data.tour');
});
