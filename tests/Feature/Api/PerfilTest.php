<?php

use App\Models\Auditoria;
use App\Models\Departamento;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;

/*
 * Mi perfil (nov. 2026). Cada usuario consulta sus datos y edita solo su
 * nombre (decisión del responsable: el correo, el rol, los departamentos y el
 * estado de la cuenta los gestiona el Administrador). El cambio de contraseña
 * sigue siendo PUT /user/password (Fortify), ahora dentro del perfil.
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->compras = Departamento::create(['nombre' => 'Compras', 'slug' => 'compras']);
    $this->admision = Departamento::create(['nombre' => 'Admisión', 'slug' => 'admision']);

    $this->usuario = User::factory()->create([
        'name' => 'Ana Pérez',
        'email' => 'ana@infotep.local',
        'role_id' => Role::where('slug', 'encargado-departamento')->value('id'),
    ]);
    $this->usuario->departamentos()->attach($this->compras);
});

// --- Consulta ---------------------------------------------------------------

test('the profile data comes from the authenticated user, including the creation date', function () {
    $this->actingAs($this->usuario)->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('data.name', 'Ana Pérez')
        ->assertJsonPath('data.email', 'ana@infotep.local')
        ->assertJsonPath('data.activo', true)
        ->assertJsonPath('data.role.slug', 'encargado-departamento')
        ->assertJsonPath('data.departamentos.0.nombre', 'Compras')
        ->assertJsonPath('data.created_at', $this->usuario->created_at->toIso8601String());
});

// --- Edición del nombre -----------------------------------------------------

test('any user can change their own name', function (string $rol) {
    $user = User::factory()->create(['role_id' => Role::where('slug', $rol)->value('id')]);

    $this->actingAs($user)->putJson('/api/perfil', ['name' => '  María José Gómez  '])
        ->assertOk()
        ->assertJsonPath('data.name', 'María José Gómez')
        ->assertJsonPath('data.role.slug', $rol);

    expect($user->fresh()->name)->toBe('María José Gómez');
})->with(['administrador', 'encargado-departamento', 'encargado-seguimiento', 'auditor', 'direccion']);

test('the name is required and limited in length', function (mixed $nombre) {
    $this->actingAs($this->usuario)->putJson('/api/perfil', ['name' => $nombre])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    expect($this->usuario->fresh()->name)->toBe('Ana Pérez');
})->with([[''], [null], [str_repeat('a', 256)], [['no', 'texto']]]);

test('protected fields can not be changed from the profile', function (string $campo, mixed $valor) {
    $this->actingAs($this->usuario)->putJson('/api/perfil', ['name' => 'Ana Pérez', $campo => $valor])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($campo);

    $fresco = $this->usuario->fresh();
    expect($fresco->email)->toBe('ana@infotep.local')
        ->and($fresco->role->slug)->toBe('encargado-departamento')
        ->and($fresco->activo)->toBeTrue()
        ->and($fresco->departamentos->pluck('id')->all())->toBe([$this->compras->id]);
})->with([
    'correo' => ['email', 'otro@infotep.local'],
    'rol' => ['role_id', 1],
    'departamentos' => ['departamento_ids', [1, 2]],
    'estado' => ['activo', false],
    'contraseña' => ['password', 'Nueva-Clave-2026!'],
    'otro usuario' => ['id', 999],
]);

test('the profile only ever changes the authenticated user', function () {
    $otro = User::factory()->create(['name' => 'Otro']);

    $this->actingAs($this->usuario)->putJson('/api/perfil', ['name' => 'Ana P.'])->assertOk();

    expect($otro->fresh()->name)->toBe('Otro');
});

test('guests can not update a profile', function () {
    $this->putJson('/api/perfil', ['name' => 'X'])->assertUnauthorized();
});

test('a deactivated user can not update the profile', function () {
    $this->usuario->update(['activo' => false]);

    $this->actingAs($this->usuario)->putJson('/api/perfil', ['name' => 'X'])->assertUnauthorized();
    expect($this->usuario->fresh()->name)->toBe('Ana Pérez');
});

test('a name change is audited as a change made by the user', function () {
    $this->actingAs($this->usuario)->putJson('/api/perfil', ['name' => 'Ana P.'])->assertOk();

    $registro = Auditoria::where(['entidad' => 'usuario', 'entidad_id' => $this->usuario->id, 'evento' => 'actualizado'])->latest('id')->firstOrFail();

    expect($registro->user_id)->toBe($this->usuario->id)
        ->and($registro->valores_anteriores)->toBe(['name' => 'Ana Pérez'])
        ->and($registro->valores_nuevos)->toBe(['name' => 'Ana P.']);
});

// --- Seguridad de la cuenta: contraseña -------------------------------------

test('a deactivated user can not change the password with an open session', function () {
    $this->usuario->update(['activo' => false]);

    $this->actingAs($this->usuario)->putJson(route('user-password.update'), [
        'current_password' => 'password',
        'password' => 'Nueva-Clave-2026!',
        'password_confirmation' => 'Nueva-Clave-2026!',
    ])->assertUnauthorized();

    expect(Hash::check('password', $this->usuario->fresh()->password))->toBeTrue();
});

test('the password change is audited without its value', function () {
    $this->actingAs($this->usuario)->putJson(route('user-password.update'), [
        'current_password' => 'password',
        'password' => 'Nueva-Clave-2026!',
        'password_confirmation' => 'Nueva-Clave-2026!',
    ])->assertOk();

    $registro = Auditoria::where(['entidad' => 'usuario', 'entidad_id' => $this->usuario->id])->latest('id')->firstOrFail();

    expect($registro->valores_nuevos)->toBe(['password' => '[oculto]'])
        ->and(json_encode($registro->toArray()))->not->toContain('Nueva-Clave-2026!');
});

test('login still works for active users after protecting the fortify routes', function () {
    $this->postJson('/login', ['email' => 'ana@infotep.local', 'password' => 'password'])->assertOk();
    $this->assertAuthenticatedAs($this->usuario);
});
