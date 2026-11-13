<?php

use App\Enums\EstadoMatriz;
use App\Models\Departamento;
use App\Models\FactorRiesgo;
use App\Models\Matriz;
use App\Models\Proceso;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Tests\Support\Escenario;

/*
 * Integración de la ampliación de nov. 2026: un mismo flujo, por la API y
 * con cada rol, que atraviesa las funcionalidades nuevas y comprueba que
 * conviven con las anteriores (ediciones, riesgos, seguimiento, reportes,
 * auditoría):
 *   1. El Encargado de Departamento crea una matriz con su primera edición.
 *   2. La completa, la publica y se registra seguimiento.
 *   3. Solo el creador (y el Administrador) edita la matriz.
 *   4. El historial de movimientos muestra lo ocurrido a quien corresponde.
 *   5. Perfil y recorrido guiado de cada usuario.
 */

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->compras = Escenario::departamento('Compras');
    $this->admision = Escenario::departamento('Admisión');
    Escenario::proceso($this->admision, 'Inscripción');

    $this->admin = Escenario::usuario('administrador');
    $this->encargado = Escenario::usuario('encargado-departamento', $this->compras);
    $this->colega = Escenario::usuario('encargado-departamento', $this->compras);
    $this->seguimiento = Escenario::usuario('encargado-seguimiento', $this->compras);
    $this->otroDepartamento = Escenario::usuario('encargado-departamento', $this->admision);
    $this->auditor = Escenario::usuario('auditor');
    $this->direccion = Escenario::usuario('direccion');
});

test('the november 2026 extension works end to end across roles', function () {
    // 1. Matriz nueva con su primera edición, en una sola operación.
    $this->actingAs($this->encargado)->getJson('/api/departamentos')
        ->assertOk()
        ->assertJsonPath('data.0.permisos.crear_proceso', true);

    $respuesta = $this->actingAs($this->encargado)->postJson("/api/departamentos/{$this->compras->id}/procesos", [
        'nombre' => 'Abastecimiento',
        'descripcion' => 'Compras de insumos',
        'edicion_inicial' => ['nombre' => 'Enero-Marzo 2027', 'fecha_revision' => '2027-01-15'],
    ])->assertCreated()->assertJsonPath('data.permisos.editar', true);

    $proceso = Proceso::findOrFail($respuesta->json('data.id'));
    $edicion = $proceso->matrices()->sole();
    expect($proceso->creado_por_id)->toBe($this->encargado->id)
        ->and($edicion->estado->value)->toBe('borrador');

    // Mismo nombre en el mismo departamento: rechazado con un mensaje claro.
    $this->actingAs($this->encargado)->postJson("/api/departamentos/{$this->compras->id}/procesos", ['nombre' => 'Abastecimiento'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.slug.0', 'Ya existe un proceso con ese nombre en este departamento.');

    // 2. Flujo existente sobre la matriz nueva: riesgo, publicación, seguimiento.
    $riesgoId = $this->actingAs($this->encargado)->postJson("/api/matrices/{$edicion->id}/riesgos", [
        'actividad' => 'Evaluación de ofertas',
        'descripcion' => 'Adjudicaciones erróneas',
        'probabilidad' => 3,
        'impacto' => 4,
        'factor_ids' => [FactorRiesgo::where('slug', 'administrativo')->value('id')],
    ])->assertCreated()->json('data.id');

    $this->actingAs($this->encargado)->postJson("/api/matrices/{$edicion->id}/publicar")->assertOk();

    $this->actingAs($this->seguimiento)->postJson("/api/riesgos/{$riesgoId}/seguimientos", [
        'fecha' => now()->toDateString(),
        'descripcion' => 'Revisión trimestral',
        'estado_tratamiento' => 'en_progreso',
    ])->assertCreated();

    // Reportes y dashboard siguen viendo el riesgo dentro del alcance de cada rol.
    $this->actingAs($this->direccion)->getJson('/api/reportes/riesgos')->assertOk()->assertJsonCount(1, 'data');
    $this->actingAs($this->otroDepartamento)->getJson('/api/reportes/riesgos')->assertOk()->assertJsonCount(0, 'data');
    $this->actingAs($this->encargado)->getJson('/api/dashboard')->assertOk();

    // 3. Editar la matriz: solo su creador y el Administrador.
    $this->actingAs($this->colega)->getJson("/api/procesos/{$proceso->id}")
        ->assertOk()
        ->assertJsonPath('data.permisos.editar', false);
    $this->actingAs($this->colega)->putJson("/api/procesos/{$proceso->id}", ['descripcion' => 'Intento'])->assertForbidden();
    $this->actingAs($this->seguimiento)->putJson("/api/procesos/{$proceso->id}", ['descripcion' => 'Intento'])->assertForbidden();
    $this->actingAs($this->encargado)->putJson("/api/procesos/{$proceso->id}", ['descripcion' => 'Insumos y materiales'])->assertOk();
    $this->actingAs($this->admin)->putJson("/api/procesos/{$proceso->id}", ['descripcion' => 'Insumos, materiales y servicios'])->assertOk();
    expect($proceso->fresh()->creado_por_id)->toBe($this->encargado->id);

    // 4. Historial de movimientos.
    $eventos = fn (User $usuario) => collect($this->actingAs($usuario)->getJson('/api/movimientos?per_page=100')->assertOk()->json('data'))
        ->map(fn (array $m) => "{$m['entidad']}:{$m['evento']}");

    foreach ([$this->encargado, $this->colega, $this->seguimiento, $this->admin] as $usuario) {
        expect($eventos($usuario))->toContain('proceso:creado', 'matriz:creado', 'riesgo:creado', 'matriz:publicado', 'seguimiento:creado', 'proceso:actualizado');
    }

    // Otro departamento no ve nada de Compras; Auditor y Dirección no tienen historial.
    $deAdmision = collect($this->actingAs($this->otroDepartamento)->getJson('/api/movimientos?per_page=100')->assertOk()->json('data'));
    expect($deAdmision->pluck('departamento.id')->unique()->all())->not->toContain($this->compras->id);
    $this->actingAs($this->auditor)->getJson('/api/movimientos')->assertForbidden();
    $this->actingAs($this->direccion)->getJson('/api/movimientos')->assertForbidden();

    // La auditoría completa sigue siendo del Administrador y el Auditor.
    $this->actingAs($this->auditor)->getJson('/api/auditoria')->assertOk();
    $this->actingAs($this->encargado)->getJson('/api/auditoria')->assertForbidden();

    // 5. Perfil y recorrido guiado.
    $this->actingAs($this->encargado)->getJson('/api/user')
        ->assertOk()
        ->assertJsonStructure(['data' => ['tour' => ['estado', 'actualizado_at']]])
        ->assertJsonPath('data.tour.estado', null);

    $this->actingAs($this->encargado)->putJson('/api/perfil', ['name' => 'Ana Encargada'])->assertOk();
    $this->actingAs($this->encargado)->putJson('/api/perfil', ['name' => 'Ana Encargada', 'email' => 'otra@infotep.gob.do'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
    $this->actingAs($this->encargado)->putJson('/api/perfil', ['name' => 'Ana Encargada', 'departamento_ids' => [$this->admision->id]])
        ->assertUnprocessable();
    expect($this->encargado->fresh()->departamentos->pluck('id')->all())->toBe([$this->compras->id]);

    $this->actingAs($this->encargado)->putJson('/api/perfil/tour', ['estado' => 'completado'])->assertOk();
    $this->actingAs($this->encargado)->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('data.name', 'Ana Encargada')
        ->assertJsonPath('data.tour.estado', 'completado');

    // El nombre nuevo aparece como autor en el historial; el tour no se audita.
    $autores = collect($this->actingAs($this->admin)->getJson('/api/movimientos?per_page=100')->json('data'))->pluck('usuario.name');
    expect($autores)->toContain('Ana Encargada');
    $this->actingAs($this->admin)->getJson('/api/auditoria?entidad=usuario')->assertOk()
        ->assertDontSee('tour_estado');

    // Un usuario desactivado pierde el acceso a todo lo nuevo: su sesión se
    // cierra (401) y la SPA lo devuelve al login.
    $this->colega->forceFill(['activo' => false])->save();
    $this->actingAs($this->colega)->getJson('/api/movimientos')->assertUnauthorized();
    $this->actingAs($this->colega)->putJson('/api/perfil/tour', ['estado' => 'omitido'])->assertUnauthorized();
    $this->actingAs($this->colega)->postJson("/api/departamentos/{$this->compras->id}/procesos", ['nombre' => 'Almacén'])->assertUnauthorized();
});

test('existing departments and processes keep working without a creator', function () {
    // Procesos anteriores a la ampliación (creado_por_id null): ningún
    // encargado los edita, el Administrador sí, y todos los siguen viendo.
    $antiguo = Escenario::proceso($this->compras, 'Proceso histórico');
    $edicion = Escenario::edicion($antiguo, 'Edición 1', estado: EstadoMatriz::Vigente, riesgos: [['p' => 2, 'i' => 2]]);

    expect($antiguo->creado_por_id)->toBeNull();

    $this->actingAs($this->encargado)->getJson("/api/procesos/{$antiguo->id}")
        ->assertOk()
        ->assertJsonPath('data.permisos.editar', false);
    $this->actingAs($this->encargado)->putJson("/api/procesos/{$antiguo->id}", ['descripcion' => 'x'])->assertForbidden();
    $this->actingAs($this->admin)->putJson("/api/procesos/{$antiguo->id}", ['descripcion' => 'x'])->assertOk();

    // El encargado sigue gestionando sus ediciones como antes.
    $this->actingAs($this->encargado)->postJson("/api/matrices/{$edicion->id}/clonar", ['nombre' => 'Edición 2', 'fecha_revision' => '2027-01-15'])
        ->assertCreated();
    expect(Matriz::where('proceso_id', $antiguo->id)->count())->toBe(2);
    expect(Departamento::visiblesPara($this->encargado)->pluck('id')->all())->toBe([$this->compras->id]);
});
