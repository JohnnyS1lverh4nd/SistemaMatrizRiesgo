<?php

use App\Models\Departamento;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;

/*
 * RF-8: gestión de usuarios (solo Administrador).
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    // Nombre fijo: el listado se ordena por nombre.
    $this->admin = User::factory()->create(['name' => 'Administrador', 'role_id' => rolId('administrador')]);
    $this->compras = Departamento::create(['nombre' => 'Compras', 'slug' => 'compras']);
    $this->admision = Departamento::create(['nombre' => 'Admisión', 'slug' => 'admision']);
});

function rolId(string $slug): int
{
    return Role::where('slug', $slug)->value('id');
}

function datosUsuarioValidos(array $cambios = []): array
{
    return [
        'name' => 'Ana Pérez',
        'email' => 'ana.perez@infotep.test',
        'password' => 'clave-segura-123',
        'password_confirmation' => 'clave-segura-123',
        'role_id' => rolId('encargado-departamento'),
        'departamento_ids' => [],
        ...$cambios,
    ];
}

// --- Autorización -----------------------------------------------------------

test('guests can not access user management', function () {
    $this->getJson('/api/users')->assertUnauthorized();
    $this->postJson('/api/users', datosUsuarioValidos())->assertUnauthorized();
});

test('non admin roles can not access user management', function (string $rol) {
    $user = User::factory()->create(['role_id' => rolId($rol)]);
    $otro = User::factory()->create();

    $this->actingAs($user)->getJson('/api/users')->assertForbidden();
    $this->actingAs($user)->getJson("/api/users/{$otro->id}")->assertForbidden();
    $this->actingAs($user)->postJson('/api/users', datosUsuarioValidos())->assertForbidden();
    $this->actingAs($user)->putJson("/api/users/{$otro->id}", ['name' => 'X'])->assertForbidden();
    $this->actingAs($user)->getJson('/api/roles')->assertForbidden();
})->with(['encargado-departamento', 'encargado-seguimiento', 'auditor', 'direccion']);

test('users can not be deleted through the api', function () {
    $otro = User::factory()->create();

    $this->actingAs($this->admin)->deleteJson("/api/users/{$otro->id}")->assertMethodNotAllowed();
    $this->assertModelExists($otro);
});

// --- Consulta ---------------------------------------------------------------

test('admin can list users with role, departamentos and status', function () {
    $encargado = User::factory()->create(['name' => 'Beatriz', 'role_id' => rolId('encargado-departamento')]);
    $encargado->departamentos()->attach($this->compras);

    $this->actingAs($this->admin)->getJson('/api/users')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.1.name', 'Beatriz')
        ->assertJsonPath('data.1.activo', true)
        ->assertJsonPath('data.1.role.slug', 'encargado-departamento')
        ->assertJsonPath('data.1.departamentos.0.slug', 'compras')
        ->assertJsonStructure(['data', 'links', 'meta']);
});

test('admin can view a single user', function () {
    $otro = User::factory()->create();

    $this->actingAs($this->admin)->getJson("/api/users/{$otro->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $otro->id)
        ->assertJsonMissingPath('data.password');
});

test('admin can list roles for the user form', function () {
    $this->actingAs($this->admin)->getJson('/api/roles')
        ->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonStructure(['data' => [['id', 'nombre', 'slug', 'descripcion']]]);
});

// --- Creación ---------------------------------------------------------------

test('admin can create a user with role and departamentos', function () {
    $response = $this->actingAs($this->admin)->postJson('/api/users', datosUsuarioValidos([
        'departamento_ids' => [$this->compras->id, $this->admision->id],
    ]));

    $response->assertCreated()
        ->assertJsonPath('data.email', 'ana.perez@infotep.test')
        ->assertJsonPath('data.activo', true)
        ->assertJsonCount(2, 'data.departamentos');

    $user = User::where('email', 'ana.perez@infotep.test')->firstOrFail();
    expect(Hash::check('clave-segura-123', $user->password))->toBeTrue()
        ->and($user->role_id)->toBe(rolId('encargado-departamento'))
        ->and($user->departamentos()->pluck('departamentos.id')->sort()->values()->all())
        ->toBe([$this->compras->id, $this->admision->id]);
});

test('user creation validates input', function (array $cambios, string $campo) {
    User::factory()->create(['email' => 'repetido@infotep.test']);

    $this->actingAs($this->admin)->postJson('/api/users', datosUsuarioValidos($cambios))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($campo);

    expect(User::where('name', 'Ana Pérez')->exists())->toBeFalse();
})->with([
    'nombre vacío' => [['name' => ''], 'name'],
    'email inválido' => [['email' => 'no-es-email'], 'email'],
    'email duplicado' => [['email' => 'repetido@infotep.test'], 'email'],
    'sin contraseña' => [['password' => '', 'password_confirmation' => ''], 'password'],
    'confirmación distinta' => [['password_confirmation' => 'otra-cosa'], 'password'],
    'rol inexistente' => [['role_id' => 9999], 'role_id'],
    'sin rol' => [['role_id' => null], 'role_id'],
    'departamento inexistente' => [['departamento_ids' => [9999]], 'departamento_ids.0'],
]);

test('mass assignment can not set unexpected attributes', function () {
    $this->actingAs($this->admin)->postJson('/api/users', datosUsuarioValidos([
        'email_verified_at' => '2000-01-01 00:00:00',
        'remember_token' => 'inyectado',
    ]))->assertCreated();

    $user = User::where('email', 'ana.perez@infotep.test')->firstOrFail();
    expect($user->remember_token)->not->toBe('inyectado')
        ->and($user->email_verified_at)->toBeNull();
});

// --- Edición ----------------------------------------------------------------

test('admin can update a user and replace its departamentos', function () {
    $user = User::factory()->create(['role_id' => rolId('encargado-departamento')]);
    $user->departamentos()->attach($this->compras);

    $this->actingAs($this->admin)->putJson("/api/users/{$user->id}", [
        'name' => 'Nombre Nuevo',
        'role_id' => rolId('encargado-seguimiento'),
        'departamento_ids' => [$this->admision->id],
    ])->assertOk()
        ->assertJsonPath('data.name', 'Nombre Nuevo')
        ->assertJsonPath('data.role.slug', 'encargado-seguimiento')
        ->assertJsonPath('data.departamentos.0.slug', 'admision')
        ->assertJsonCount(1, 'data.departamentos');
});

test('updating without password keeps the current password', function () {
    $user = User::factory()->create();
    $hashAnterior = $user->password;

    $this->actingAs($this->admin)->putJson("/api/users/{$user->id}", ['name' => 'Otro'])->assertOk();

    expect($user->fresh()->password)->toBe($hashAnterior);
});

test('admin can change a user password', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)->putJson("/api/users/{$user->id}", [
        'password' => 'nueva-clave-456',
        'password_confirmation' => 'nueva-clave-456',
    ])->assertOk();

    expect(Hash::check('nueva-clave-456', $user->fresh()->password))->toBeTrue();
});

test('email must stay unique on update', function () {
    User::factory()->create(['email' => 'ocupado@infotep.test']);
    $user = User::factory()->create();

    $this->actingAs($this->admin)->putJson("/api/users/{$user->id}", ['email' => 'ocupado@infotep.test'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

// --- Desactivación ----------------------------------------------------------

test('admin can deactivate and reactivate a user', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)->putJson("/api/users/{$user->id}", ['activo' => false])
        ->assertOk()->assertJsonPath('data.activo', false);
    expect($user->fresh()->activo)->toBeFalse();

    $this->actingAs($this->admin)->putJson("/api/users/{$user->id}", ['activo' => true])
        ->assertOk()->assertJsonPath('data.activo', true);
});

test('admin can not deactivate themselves', function () {
    $this->actingAs($this->admin)->putJson("/api/users/{$this->admin->id}", ['activo' => false])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('activo');

    expect($this->admin->fresh()->activo)->toBeTrue();
});

test('admin can not remove their own administrator role', function () {
    $this->actingAs($this->admin)->putJson("/api/users/{$this->admin->id}", ['role_id' => rolId('auditor')])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('role_id');

    expect($this->admin->fresh()->role_id)->toBe(rolId('administrador'));
});

test('admin can still edit their own name', function () {
    $this->actingAs($this->admin)->putJson("/api/users/{$this->admin->id}", [
        'name' => 'Admin Renombrado',
        'role_id' => rolId('administrador'),
        'activo' => true,
    ])->assertOk();
});
