<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Auditor;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->limitarPeticiones();
    }

    /**
     * Límites por usuario (o por IP si no hay sesión). "api" se aplica a todo
     * el grupo api; "operaciones-costosas" a exportar e importar, que generan
     * archivos y pueden saturar el servidor.
     */
    protected function limitarPeticiones(): void
    {
        $clave = fn (Request $request): string => (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(300)->by($clave($request)));
        RateLimiter::for('operaciones-costosas', fn (Request $request) => Limit::perMinute(20)->by($clave($request)));
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

        // Producción: mínimo 8 caracteres (decisión oct. 2026) con mayúsculas,
        // minúsculas, números, símbolos y sin filtraciones conocidas.
        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(8)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
