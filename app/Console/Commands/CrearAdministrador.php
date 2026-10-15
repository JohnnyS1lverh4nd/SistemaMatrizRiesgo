<?php

namespace App\Console\Commands;

use App\Concerns\PasswordValidationRules;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Alta de un Administrador desde la consola: el primero de una instalación
 * nueva, donde no hay registro público ni usuarios sembrados.
 *
 *   php artisan usuarios:crear-administrador
 *
 * La contraseña se pide oculta (nunca como argumento: quedaría en el
 * historial de la terminal) y cumple las mismas reglas que en la aplicación.
 */
class CrearAdministrador extends Command
{
    use PasswordValidationRules;

    protected $signature = 'usuarios:crear-administrador';

    protected $description = 'Crea un usuario Administrador (p. ej. el primero de una instalación nueva)';

    public function handle(): int
    {
        $rol = Role::where('slug', 'administrador')->first();

        if ($rol === null) {
            $this->error('No existen los roles. Ejecute primero: php artisan db:seed --force');

            return self::FAILURE;
        }

        $datos = [
            'name' => trim((string) $this->ask('Nombre completo')),
            'email' => trim((string) $this->ask('Correo electrónico')),
            'password' => (string) $this->secret('Contraseña'),
            'password_confirmation' => (string) $this->secret('Repita la contraseña'),
        ];

        $validador = Validator::make($datos, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => $this->passwordRules(),
        ]);

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $usuario = User::create([
            'name' => $datos['name'],
            'email' => $datos['email'],
            'password' => $datos['password'],
            'role_id' => $rol->id,
            'activo' => true,
        ]);

        $this->info("Administrador creado: {$usuario->email}");

        return self::SUCCESS;
    }
}
