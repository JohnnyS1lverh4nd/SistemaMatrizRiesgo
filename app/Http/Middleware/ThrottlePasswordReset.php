<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fortify registra POST /forgot-password y POST /reset-password sin límite
 * por IP (solo el broker limita por usuario). Este middleware, añadido al
 * grupo web, les aplica el limitador 'password-reset' sin redefinir las rutas.
 */
class ThrottlePasswordReset
{
    private const RUTAS = ['password.email', 'password.update'];

    public function __construct(private readonly ThrottleRequests $throttle) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs(...self::RUTAS)) {
            return $next($request);
        }

        return $this->throttle->handle($request, $next, 'password-reset');
    }
}
