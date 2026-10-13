<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\FailedPasswordResetResponse;
use App\Http\Responses\PasswordResetLinkRequestedResponse;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetResponse as FailedPasswordResetResponseContract;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Fortify instancia estas respuestas con ['status' => ...]; el constructor
        // propio no lo necesita, por eso se registran con un closure.
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, fn () => new PasswordResetLinkRequestedResponse);
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, fn () => new PasswordResetLinkRequestedResponse);
        $this->app->bind(FailedPasswordResetResponseContract::class, fn () => new FailedPasswordResetResponse);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // Igual que la autenticación por defecto, pero rechaza usuarios desactivados.
        // Devuelve null (mismo error que credenciales incorrectas) para no revelar
        // el estado de la cuenta.
        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::where(Fortify::username(), $request->input(Fortify::username()))->first();

            if ($user === null || ! Hash::check((string) $request->input('password'), $user->password)) {
                return null;
            }

            return $user->activo ? $user : null;
        });

        // Las vistas de Fortify están desactivadas: el enlace del correo debe
        // abrir la página de Nuxt, que luego envía el token a POST /reset-password.
        ResetPassword::createUrlUsing(fn (User $user, string $token): string => config('app.frontend_url')
            .'/restablecer-contrasena?'
            .http_build_query(['token' => $token, 'email' => $user->getEmailForPasswordReset()]));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}