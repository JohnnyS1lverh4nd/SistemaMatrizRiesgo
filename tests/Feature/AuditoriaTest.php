<?php

use App\Models\Auditoria;
use App\Models\Departamento;
use App\Models\FactorRiesgo;
use App\Models\Matriz;
use App\Models\MatrizCalorCelda;
use App\Models\NivelRiesgo;
use App\Models\Proceso;
use App\Models\Riesgo;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

/*
 * Fase 7: auditoría (RF-9). Decisiones (oct. 2026): se audita todo lo que
 * cambia datos más los inicios de sesión; la consultan Administrador y
 * Auditor; se conserva indefinidamente (solo inserción).
 */

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->compras = Departamento::create(['nombre' => 'Compras', 'slug' => 'compras']);
    $this->admision = Departamento::create(['nombre' => 'Admisión', 'slug' => 'admision']);
    $this->proceso = Proceso::create(['departamento_id' => $this->compras->id, 'nombre' => 'Abastecimiento', 'slug' => 'abastecimiento']);

    $this->admin = auditado('administrador');
    $this->encargado = auditado('encargado-departamento', $this->compras);
    $this->auditor = auditado('auditor');
});

function auditado(string $rol, ?Departamento $departamento = null): User
{
    $user = User::factory()->create(['role_id' => Role::where('slug', $rol)->value('id')]);
    if ($departamento) {
        $user->departamentos()->attach($departamento);
    }

    return $user;
}

function ultimaAuditoria(string $entidad, ?string $evento = null): Auditoria
{
    return Auditoria::where('entidad', $entidad)
        ->when($evento, fn ($q) => $q->where('evento', $evento))
        ->latest('id')
        ->firstOrFail();
}

function borradorConRiesgo(): array
{
    $matrizId = test()->actingAs(test()->encargado)
        ->postJson('/api/procesos/'.test()->proceso->id.'/matrices', ['nombre' => 'Abril-Junio 2026', 'fecha_revision' => '2026-06-30'])
        ->json('data.id');
    $riesgoId = test()->actingAs(test()->encargado)->postJson("/api/matrices/{$matrizId}/riesgos", [
        'actividad' => 'Evaluación de ofertas',
        'descripcion' => 'Adjudicaciones erróneas',
        'probabilidad' => 3,
        'impacto' => 4,
        'factor_ids' => [FactorRiesgo::where('slug', 'administrativo')->value('id')],
    ])->json('data.id');

    return [Matriz::findOrFail($matrizId), Riesgo::findOrFail($riesgoId)];
}

// --- Qué se registra --------------------------------------------------------

test('creating a risk records who, when, from where and the new values', function () {
    [, $riesgo] = borradorConRiesgo();

    $registro = ultimaAuditoria('riesgo', 'creado');

    expect($registro->entidad_id)->toBe($riesgo->id)
        ->and($registro->user_id)->toBe($this->encargado->id)
        ->and($registro->ip)->toBe('127.0.0.1')
        ->and($registro->created_at)->not->toBeNull()
        ->and($registro->valores_anteriores)->toBeNull()
        ->and($registro->valores_nuevos['descripcion'])->toBe('Adjudicaciones erróneas')
        ->and($registro->valores_nuevos['probabilidad'])->toBe(3);
});

test('updates record only the changed fields with old and new values', function () {
    [, $riesgo] = borradorConRiesgo();

    $this->actingAs($this->encargado)->putJson("/api/riesgos/{$riesgo->id}", ['probabilidad' => 1, 'impacto' => 1])->assertOk();

    $registro = ultimaAuditoria('riesgo', 'actualizado');
    expect($registro->valores_anteriores)->toMatchArray(['probabilidad' => 3, 'impacto' => 4])
        ->and($registro->valores_nuevos)->toMatchArray(['probabilidad' => 1, 'impacto' => 1])
        ->and($registro->valores_nuevos)->toHaveKey('nivel_calculado_id')
        ->and($registro->valores_nuevos)->not->toHaveKey('updated_at')
        ->and($registro->valores_nuevos)->not->toHaveKey('descripcion');
});

test('changes in the factors of a risk are recorded', function () {
    [, $riesgo] = borradorConRiesgo();
    $administrativo = FactorRiesgo::where('slug', 'administrativo')->value('id');
    $tecnologico = FactorRiesgo::where('slug', 'tecnologico')->value('id');

    $this->actingAs($this->encargado)->putJson("/api/riesgos/{$riesgo->id}", ['factor_ids' => [$tecnologico]])->assertOk();

    $registro = ultimaAuditoria('riesgo', 'actualizado');
    expect($registro->valores_anteriores)->toBe(['factor_ids' => [$administrativo]])
        ->and($registro->valores_nuevos)->toBe(['factor_ids' => [$tecnologico]]);
});

test('soft deleting a risk is recorded', function () {
    [, $riesgo] = borradorConRiesgo();

    $this->actingAs($this->encargado)->deleteJson("/api/riesgos/{$riesgo->id}")->assertNoContent();

    expect(ultimaAuditoria('riesgo', 'eliminado')->entidad_id)->toBe($riesgo->id);
});

test('edition lifecycle operations are recorded with their own names', function () {
    [$matriz] = borradorConRiesgo();
    $this->actingAs($this->encargado)->postJson("/api/matrices/{$matriz->id}/publicar")->assertOk();
    $copia = $this->actingAs($this->encargado)->postJson("/api/matrices/{$matriz->id}/clonar", ['nombre' => 'Copia', 'fecha_revision' => '2026-09-30'])->json('data.id');
    $this->actingAs($this->encargado)->postJson("/api/matrices/{$copia}/publicar")->assertOk();

    $publicado = Auditoria::where(['entidad' => 'matriz', 'entidad_id' => $matriz->id, 'evento' => 'publicado'])->firstOrFail();
    expect($publicado->valores_anteriores['estado'])->toBe('borrador')
        ->and($publicado->valores_nuevos['estado'])->toBe('vigente');

    expect(Auditoria::where(['entidad' => 'matriz', 'entidad_id' => $copia, 'evento' => 'clonado'])->exists())->toBeTrue()
        ->and(Auditoria::where(['entidad' => 'matriz', 'entidad_id' => $matriz->id, 'evento' => 'archivado'])->exists())->toBeTrue();
});

test('voiding a follow up entry is recorded', function () {
    [$matriz, $riesgo] = borradorConRiesgo();
    $this->actingAs($this->encargado)->postJson("/api/matrices/{$matriz->id}/publicar")->assertOk();
    $id = $this->actingAs($this->encargado)->postJson("/api/riesgos/{$riesgo->id}/seguimientos", ['fecha' => '2026-04-01', 'descripcion' => 'Revisión'])->json('data.id');

    $this->actingAs($this->encargado)->postJson("/api/seguimientos/{$id}/anular", ['motivo' => 'Duplicado'])->assertOk();

    $registro = ultimaAuditoria('seguimiento', 'anulado');
    expect($registro->entidad_id)->toBe($id)
        ->and($registro->valores_nuevos['motivo_anulacion'])->toBe('Duplicado');
});

test('heat map changes are recorded because they change how levels are calculated', function () {
    $celda = MatrizCalorCelda::where(['probabilidad' => 4, 'impacto' => 4])->firstOrFail();
    $bajo = NivelRiesgo::where('slug', 'bajo')->value('id');

    $this->actingAs($this->admin)->putJson("/api/matriz-calor-celdas/{$celda->id}", ['nivel_riesgo_id' => $bajo])->assertOk();

    $registro = ultimaAuditoria('matriz_calor_celda', 'actualizado');
    expect($registro->user_id)->toBe($this->admin->id)
        ->and($registro->valores_nuevos['nivel_riesgo_id'])->toBe($bajo);
});

test('user changes are recorded without exposing passwords or tokens', function () {
    $user = auditado('encargado-departamento', $this->compras);

    $this->actingAs($this->admin)->putJson("/api/users/{$user->id}", [
        'password' => 'nueva-clave-123',
        'password_confirmation' => 'nueva-clave-123',
        'departamento_ids' => [$this->admision->id],
    ])->assertOk();

    $registros = Auditoria::where(['entidad' => 'usuario', 'entidad_id' => $user->id])->get();
    $json = $registros->toJson();

    expect($json)->not->toContain('nueva-clave-123')
        ->and($json)->not->toContain($user->fresh()->password)
        ->and($json)->not->toContain('remember_token');

    $cambioClave = $registros->firstWhere(fn ($r) => array_key_exists('password', $r->valores_nuevos ?? []));
    expect($cambioClave->valores_nuevos['password'])->toBe('[oculto]');

    $cambioDepartamentos = $registros->firstWhere(fn ($r) => array_key_exists('departamento_ids', $r->valores_nuevos ?? []));
    expect($cambioDepartamentos->valores_anteriores['departamento_ids'])->toBe([$this->compras->id])
        ->and($cambioDepartamentos->valores_nuevos['departamento_ids'])->toBe([$this->admision->id]);
});

test('successful and failed logins are recorded, never the password', function () {
    $user = User::factory()->create();

    $this->postJson('/login', ['email' => $user->email, 'password' => 'incorrecta-123'])->assertUnprocessable();
    $fallido = Auditoria::where('evento', 'login_fallido')->latest('id')->firstOrFail();
    expect($fallido->user_id)->toBeNull()
        ->and($fallido->descripcion)->toContain($user->email)
        ->and(json_encode($fallido->toArray()))->not->toContain('incorrecta-123');

    $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
    expect(Auditoria::where(['evento' => 'login', 'user_id' => $user->id])->exists())->toBeTrue();
});

// --- Protección -------------------------------------------------------------

test('audit records can not be modified or deleted', function () {
    borradorConRiesgo();
    $registro = Auditoria::latest('id')->firstOrFail();

    expect(fn () => $registro->forceFill(['evento' => 'otro'])->save())->toThrow(LogicException::class)
        ->and(fn () => $registro->delete())->toThrow(LogicException::class);
});

// --- Consulta ---------------------------------------------------------------

test('admin and auditor can browse and filter the audit log', function (string $quien) {
    borradorConRiesgo();

    $this->actingAs($this->{$quien})->getJson('/api/auditoria?entidad=riesgo&evento=creado')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.entidad', 'riesgo')
        ->assertJsonPath('data.0.usuario.id', $this->encargado->id)
        ->assertJsonStructure(['data' => [['id', 'fecha', 'evento', 'entidad', 'entidad_id', 'usuario', 'ip', 'valores_anteriores', 'valores_nuevos']], 'meta']);
})->with(['admin', 'auditor']);

test('other roles can not browse the full audit log', function () {
    $this->actingAs($this->encargado)->getJson('/api/auditoria')->assertForbidden();

    $this->app['auth']->forgetGuards();
    $this->getJson('/api/auditoria')->assertUnauthorized();
});

test('the change history of a risk is visible to whoever can see the risk', function () {
    [, $riesgo] = borradorConRiesgo();
    $this->actingAs($this->encargado)->putJson("/api/riesgos/{$riesgo->id}", ['descripcion' => 'Corregida'])->assertOk();

    // Creación, asignación de factores y la corrección; la más reciente primero.
    $this->actingAs($this->encargado)->getJson("/api/riesgos/{$riesgo->id}/historial")
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.evento', 'actualizado')
        ->assertJsonPath('data.0.valores_nuevos.descripcion', 'Corregida')
        ->assertJsonPath('data.1.valores_nuevos.factor_ids.0', FactorRiesgo::where('slug', 'administrativo')->value('id'))
        ->assertJsonPath('data.2.evento', 'creado');

    $ajeno = auditado('encargado-departamento', $this->admision);
    $this->actingAs($ajeno)->getJson("/api/riesgos/{$riesgo->id}/historial")->assertForbidden();
});

test('the change history of an edition is visible to whoever can see it', function () {
    [$matriz] = borradorConRiesgo();
    $this->actingAs($this->encargado)->postJson("/api/matrices/{$matriz->id}/publicar")->assertOk();

    $this->actingAs($this->auditor)->getJson("/api/matrices/{$matriz->id}/historial")
        ->assertOk()
        ->assertJsonPath('data.0.evento', 'publicado');
});

test('audit log can be filtered by date range', function () {
    borradorConRiesgo();

    $this->actingAs($this->admin)->getJson('/api/auditoria?desde=2000-01-01&hasta='.now()->toDateString())
        ->assertOk()
        ->assertJsonPath('meta.total', fn ($total) => $total > 0);
    $this->actingAs($this->admin)->getJson('/api/auditoria?desde='.now()->addDay()->toDateString())
        ->assertOk()
        ->assertJsonCount(0, 'data');
    $this->actingAs($this->admin)->getJson('/api/auditoria?desde=ayer')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('desde');
});
