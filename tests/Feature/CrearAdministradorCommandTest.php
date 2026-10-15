<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

/*
 * Fase 10: alta del primer administrador en producción, donde no hay
 * registro público ni usuarios de prueba.
 */

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('the database seeder only loads catalogues, never users', function () {
    // En producción un usuario sembrado sería una cuenta con contraseña conocida.
    expect(User::count())->toBe(0);
});

test('it creates an active administrator with the password typed in secret', function () {
    $this->artisan('usuarios:crear-administrador')
        ->expectsQuestion('Nombre completo', 'Ana Pérez')
        ->expectsQuestion('Correo electrónico', 'ana@infotep.test')
        ->expectsQuestion('Contraseña', 'Clave-Segura-2026')
        ->expectsQuestion('Repita la contraseña', 'Clave-Segura-2026')
        ->expectsOutputToContain('Administrador creado: ana@infotep.test')
        ->assertSuccessful();

    $usuario = User::with('role')->sole();

    expect($usuario->name)->toBe('Ana Pérez')
        ->and($usuario->role->slug)->toBe('administrador')
        ->and($usuario->activo)->toBeTrue()
        ->and(Hash::check('Clave-Segura-2026', $usuario->password))->toBeTrue();
});

test('invalid data is rejected and nothing is created', function (array $respuestas, string $error) {
    User::factory()->create(['email' => 'existe@infotep.test']);

    $this->artisan('usuarios:crear-administrador')
        ->expectsQuestion('Nombre completo', $respuestas[0])
        ->expectsQuestion('Correo electrónico', $respuestas[1])
        ->expectsQuestion('Contraseña', $respuestas[2])
        ->expectsQuestion('Repita la contraseña', $respuestas[3])
        ->expectsOutputToContain($error)
        ->assertFailed();

    expect(User::count())->toBe(1);
})->with([
    'correo repetido' => [['Ana', 'existe@infotep.test', 'Clave-Segura-2026', 'Clave-Segura-2026'], 'El valor del campo correo electrónico ya está en uso.'],
    'correo inválido' => [['Ana', 'no-es-correo', 'Clave-Segura-2026', 'Clave-Segura-2026'], 'debe ser una dirección de correo válida'],
    'contraseñas distintas' => [['Ana', 'ana@infotep.test', 'Clave-Segura-2026', 'Otra-Clave-2026'], 'La confirmación del campo contraseña no coincide.'],
    'sin nombre' => [['', 'ana@infotep.test', 'Clave-Segura-2026', 'Clave-Segura-2026'], 'El campo nombre es obligatorio.'],
]);
