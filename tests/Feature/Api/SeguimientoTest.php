<?php

use App\Enums\EstadoMatriz;
use App\Enums\EstadoTratamiento;
use App\Models\Departamento;
use App\Models\Evidencia;
use App\Models\Matriz;
use App\Models\Proceso;
use App\Models\Riesgo;
use App\Models\Role;
use App\Models\Seguimiento;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/*
 * Fase 6: seguimiento y evidencias (RF-4).
 *
 * Decisiones (oct. 2026): registran el Encargado de Seguimiento, el Encargado
 * de Departamento (en sus departamentos) y el Administrador; solo en
 * ediciones vigentes; el histórico no se edita: un error se anula con motivo;
 * evidencias PDF, JPG/PNG y XLSX/XLS de hasta 10 MB, máximo 5 por registro.
 */

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    Storage::fake('local');

    $this->compras = Departamento::create(['nombre' => 'Compras', 'slug' => 'compras']);
    $this->admision = Departamento::create(['nombre' => 'Admisión', 'slug' => 'admision']);
    $proceso = Proceso::create(['departamento_id' => $this->compras->id, 'nombre' => 'Abastecimiento', 'slug' => 'abastecimiento']);
    $procesoAjeno = Proceso::create(['departamento_id' => $this->admision->id, 'nombre' => 'Formación', 'slug' => 'formacion']);

    $this->vigente = matrizConRiesgo($proceso, EstadoMatriz::Vigente);
    $this->riesgo = $this->vigente->riesgos()->firstOrFail();
    $this->riesgoAjeno = matrizConRiesgo($procesoAjeno, EstadoMatriz::Vigente)->riesgos()->firstOrFail();

    $this->seguimiento = usuarioDe('encargado-seguimiento', $this->compras);
    $this->encargado = usuarioDe('encargado-departamento', $this->compras);
    $this->admin = usuarioDe('administrador');
});

function usuarioDe(string $rol, ?Departamento $departamento = null): User
{
    $user = User::factory()->create(['role_id' => Role::where('slug', $rol)->value('id')]);
    if ($departamento) {
        $user->departamentos()->attach($departamento);
    }

    return $user;
}

/** Edición con un riesgo evaluado, creada en borrador y llevada al estado pedido. */
function matrizConRiesgo(Proceso $proceso, EstadoMatriz $estado): Matriz
{
    $matriz = Matriz::create(['proceso_id' => $proceso->id, 'nombre' => 'Enero-Marzo 2026', 'fecha_revision' => '2026-03-31']);

    $riesgo = new Riesgo(['actividad' => 'Evaluación de ofertas', 'descripcion' => 'Adjudicaciones erróneas', 'probabilidad' => 3, 'impacto' => 4]);
    $riesgo->matriz_id = $matriz->id;
    $riesgo->orden = 1;
    $riesgo->save();

    foreach ([EstadoMatriz::Vigente, EstadoMatriz::Archivada] as $paso) {
        if ($estado === EstadoMatriz::Borrador) {
            break;
        }
        $matriz->forceFill(['estado' => $paso])->save();
        if ($paso === $estado) {
            break;
        }
    }

    return $matriz;
}

function registrar(User $user, Riesgo $riesgo, array $datos = []): TestResponse
{
    return test()->actingAs($user)->post("/api/riesgos/{$riesgo->id}/seguimientos", [
        'fecha' => '2026-04-15',
        'descripcion' => 'Se verificaron las requisiciones aprobadas del trimestre.',
        ...$datos,
    ], ['Accept' => 'application/json']);
}

// --- Registro -----------------------------------------------------------------

test('roles that follow up can register an entry in their departamento', function (string $rol) {
    $user = $rol === 'administrador' ? $this->admin : usuarioDe($rol, $this->compras);

    registrar($user, $this->riesgo)
        ->assertCreated()
        ->assertJsonPath('data.riesgo_id', $this->riesgo->id)
        ->assertJsonPath('data.fecha', '2026-04-15')
        ->assertJsonPath('data.registrado_por.id', $user->id)
        ->assertJsonPath('data.anulado', false);
})->with(['encargado-seguimiento', 'encargado-departamento', 'administrador']);

test('the registering user comes from the session, not from the request', function () {
    registrar($this->seguimiento, $this->riesgo, ['user_id' => $this->admin->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('user_id');
});

test('a follow up entry can change the treatment state and keeps the history', function () {
    registrar($this->seguimiento, $this->riesgo, ['estado_tratamiento' => 'en_progreso'])
        ->assertCreated()
        ->assertJsonPath('data.estado_tratamiento_anterior', 'pendiente')
        ->assertJsonPath('data.estado_tratamiento_nuevo', 'en_progreso');

    registrar($this->seguimiento, $this->riesgo, ['estado_tratamiento' => 'cumplido'])->assertCreated();

    expect($this->riesgo->fresh()->estado_tratamiento)->toBe(EstadoTratamiento::Cumplido);

    $this->actingAs($this->encargado)->getJson("/api/riesgos/{$this->riesgo->id}/seguimientos")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        // Más reciente primero.
        ->assertJsonPath('data.0.estado_tratamiento_nuevo', 'cumplido')
        ->assertJsonPath('data.1.estado_tratamiento_nuevo', 'en_progreso');
});

test('follow up input is validated', function (array $datos, string $campo) {
    registrar($this->seguimiento, $this->riesgo, $datos)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($campo);
})->with([
    'sin descripción' => [['descripcion' => ''], 'descripcion'],
    'sin fecha' => [['fecha' => ''], 'fecha'],
    'fecha futura' => [['fecha' => now()->addDays(2)->toDateString()], 'fecha'],
    'estado inválido' => [['estado_tratamiento' => 'terminado'], 'estado_tratamiento'],
]);

test('follow up is only registered on current editions', function (EstadoMatriz $estado) {
    $proceso = Proceso::create(['departamento_id' => $this->compras->id, 'nombre' => 'Otro', 'slug' => 'otro']);
    $riesgo = matrizConRiesgo($proceso, $estado)->riesgos()->firstOrFail();

    registrar($this->admin, $riesgo)->assertForbidden();
})->with([EstadoMatriz::Borrador, EstadoMatriz::Archivada]);

test('users can not register follow up outside their departamentos or role', function () {
    registrar($this->seguimiento, $this->riesgoAjeno)->assertForbidden();
    registrar(usuarioDe('auditor'), $this->riesgo)->assertForbidden();
    registrar(usuarioDe('direccion'), $this->riesgo)->assertForbidden();
});

test('read only roles can read the follow up history', function (string $rol) {
    registrar($this->seguimiento, $this->riesgo)->assertCreated();

    $this->actingAs(usuarioDe($rol))->getJson("/api/riesgos/{$this->riesgo->id}/seguimientos")
        ->assertOk()
        ->assertJsonCount(1, 'data');
})->with(['auditor', 'direccion']);

test('encargados can not read the follow up of other departamentos', function () {
    $this->actingAs($this->seguimiento)->getJson("/api/riesgos/{$this->riesgoAjeno->id}/seguimientos")->assertForbidden();
});

test('the risk tells the interface whether the user can register follow up', function () {
    $this->actingAs($this->seguimiento)->getJson("/api/riesgos/{$this->riesgo->id}")
        ->assertJsonPath('data.permisos.registrar_seguimiento', true);
    $this->actingAs(usuarioDe('auditor'))->getJson("/api/riesgos/{$this->riesgo->id}")
        ->assertJsonPath('data.permisos.registrar_seguimiento', false);
});

// --- Evidencias ---------------------------------------------------------------

test('evidence files are stored privately and listed with the entry', function () {
    $respuesta = registrar($this->seguimiento, $this->riesgo, ['archivos' => [
        UploadedFile::fake()->create('acta-revision.pdf', 300, 'application/pdf'),
        UploadedFile::fake()->create('foto-almacen.jpg', 80, 'image/jpeg'),
        UploadedFile::fake()->create('inventario.xlsx', 50, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
    ]])->assertCreated()
        ->assertJsonCount(3, 'data.evidencias')
        ->assertJsonPath('data.evidencias.0.nombre', 'acta-revision.pdf');

    $ruta = Evidencia::firstOrFail()->ruta;
    Storage::disk('local')->assertExists($ruta);
    // El nombre original nunca forma parte de la ruta en disco.
    expect($ruta)->not->toContain('acta-revision')
        ->and($respuesta->json('data.evidencias.0'))->not->toHaveKey('ruta');
});

test('evidence files are validated', function (UploadedFile $archivo) {
    registrar($this->seguimiento, $this->riesgo, ['archivos' => [$archivo]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('archivos.0');
})->with([
    'ejecutable' => fn () => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'),
    'php' => fn () => UploadedFile::fake()->create('shell.php', 1, 'text/x-php'),
    'word' => fn () => UploadedFile::fake()->create('informe.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
    'más de 10 MB' => fn () => UploadedFile::fake()->create('grande.pdf', 10241, 'application/pdf'),
]);

test('file type is checked from its real content, not from its name', function () {
    // Archivo real (no fake): un script PHP renombrado como PDF.
    $ruta = tempnam(sys_get_temp_dir(), 'evidencia');
    file_put_contents($ruta, "<?php system(\$_GET['c']); ?>\n");
    $disfrazado = new UploadedFile($ruta, 'acta.pdf', null, null, true);

    registrar($this->seguimiento, $this->riesgo, ['archivos' => [$disfrazado]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('archivos.0');

    // Y un PDF real sí se acepta.
    $pdf = tempnam(sys_get_temp_dir(), 'evidencia');
    file_put_contents($pdf, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");

    registrar($this->seguimiento, $this->riesgo, ['archivos' => [new UploadedFile($pdf, 'acta.pdf', null, null, true)]])
        ->assertCreated();
});

test('at most five evidence files per entry', function () {
    $archivos = array_map(fn ($i) => UploadedFile::fake()->create("doc{$i}.pdf", 10, 'application/pdf'), range(1, 6));

    registrar($this->seguimiento, $this->riesgo, ['archivos' => $archivos])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('archivos');
});

test('evidence can be downloaded only by users who can see the risk', function () {
    registrar($this->seguimiento, $this->riesgo, ['archivos' => [
        UploadedFile::fake()->create('acta.pdf', 20, 'application/pdf'),
    ]])->assertCreated();
    $evidencia = Evidencia::firstOrFail();

    $this->actingAs(usuarioDe('auditor'))->get("/api/evidencias/{$evidencia->id}/descargar")
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertDownload('acta.pdf');

    $ajeno = usuarioDe('encargado-seguimiento', $this->admision);
    $this->actingAs($ajeno)->getJson("/api/evidencias/{$evidencia->id}/descargar")->assertForbidden();

    // Simula una petición sin sesión.
    $this->app['auth']->forgetGuards();
    $this->getJson("/api/evidencias/{$evidencia->id}/descargar")->assertUnauthorized();
});

test('the private disk is not served by url, only through the authorized endpoint', function () {
    expect(Route::has('storage.local'))->toBeFalse()
        ->and(Route::has('storage.local.upload'))->toBeFalse();

    registrar($this->seguimiento, $this->riesgo, ['archivos' => [
        UploadedFile::fake()->create('acta.pdf', 20, 'application/pdf'),
    ]])->assertCreated();
    $ruta = Evidencia::firstOrFail()->ruta;

    $this->actingAs($this->admin)->get("/storage/{$ruta}")->assertNotFound();
});

// --- Histórico inmutable y anulación -------------------------------------------

test('follow up entries can not be edited or deleted', function () {
    $id = registrar($this->seguimiento, $this->riesgo)->json('data.id');

    $this->actingAs($this->admin)->putJson("/api/seguimientos/{$id}", ['descripcion' => 'Cambiada'])->assertMethodNotAllowed();
    $this->actingAs($this->admin)->deleteJson("/api/seguimientos/{$id}")->assertMethodNotAllowed();

    $seguimiento = Seguimiento::findOrFail($id);
    // forceFill: salta la protección de asignación masiva para comprobar la del modelo.
    expect(fn () => $seguimiento->forceFill(['descripcion' => 'Por código'])->save())->toThrow(LogicException::class)
        ->and(fn () => $seguimiento->delete())->toThrow(LogicException::class);
});

test('an entry is voided with a reason and stays in the history', function () {
    $id = registrar($this->seguimiento, $this->riesgo)->json('data.id');

    $this->actingAs($this->encargado)->postJson("/api/seguimientos/{$id}/anular", ['motivo' => 'Se registró en el riesgo equivocado.'])
        ->assertOk()
        ->assertJsonPath('data.anulado', true)
        ->assertJsonPath('data.motivo_anulacion', 'Se registró en el riesgo equivocado.')
        ->assertJsonPath('data.anulado_por.id', $this->encargado->id);

    $this->actingAs($this->encargado)->getJson("/api/riesgos/{$this->riesgo->id}/seguimientos")
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.descripcion', 'Se verificaron las requisiciones aprobadas del trimestre.');
});

test('voiding requires a reason and can not be repeated', function () {
    $id = registrar($this->seguimiento, $this->riesgo)->json('data.id');

    $this->actingAs($this->encargado)->postJson("/api/seguimientos/{$id}/anular", ['motivo' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('motivo');

    $this->actingAs($this->encargado)->postJson("/api/seguimientos/{$id}/anular", ['motivo' => 'Error'])->assertOk();
    $this->actingAs($this->encargado)->postJson("/api/seguimientos/{$id}/anular", ['motivo' => 'Otra vez'])->assertForbidden();
});

test('voiding the latest state change restores the previous treatment state', function () {
    registrar($this->seguimiento, $this->riesgo, ['estado_tratamiento' => 'en_progreso']);
    $id = registrar($this->seguimiento, $this->riesgo, ['estado_tratamiento' => 'cumplido'])->json('data.id');

    $this->actingAs($this->seguimiento)->postJson("/api/seguimientos/{$id}/anular", ['motivo' => 'Aún no se cumple.'])->assertOk();

    expect($this->riesgo->fresh()->estado_tratamiento)->toBe(EstadoTratamiento::EnProgreso);
});

test('users outside the departamento can not void entries', function () {
    $id = registrar($this->seguimiento, $this->riesgo)->json('data.id');

    $this->actingAs(usuarioDe('encargado-seguimiento', $this->admision))
        ->postJson("/api/seguimientos/{$id}/anular", ['motivo' => 'X'])
        ->assertForbidden();
});
