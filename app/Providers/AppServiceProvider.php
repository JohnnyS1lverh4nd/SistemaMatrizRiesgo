<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Auditor;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->auditarAutenticacion();
    }

    /**
     * Inicios y cierres de sesión en la auditoría (RF-9). Del intento fallido
     * solo se guarda el email: nunca la contraseña.
     */
    protected function auditarAutenticacion(): void
    {
        Event::listen(Login::class, function (Login $evento) {
            if ($evento->user instanceof User) {
                Auditor::registrar('login', 'usuario', $evento->user->id, usuario: $evento->user);
            }
        });

        Event::listen(Failed::class, function (Failed $evento) {
            $email = is_string($evento->credentials['email'] ?? null) ? $evento->credentials['email'] : '(sin email)';
            Auditor::registrar('login_fallido', 'usuario', null, descripcion: "Intento de inicio de sesión fallido para {$email}");
        });

        Event::listen(Logout::class, function (Logout $evento) {
            if ($evento->user instanceof User) {
                Auditor::registrar('logout', 'usuario', $evento->user->id, usuario: $evento->user);
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
