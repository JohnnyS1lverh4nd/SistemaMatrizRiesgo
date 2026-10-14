<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

class CurrentUserController extends Controller
{
    public function __invoke(Request $request): UserResource
    {
        return (new UserResource(
            $request->user()->load(['role', 'departamentos' => fn ($query) => $query->orderBy('nombre')])
        ))->additional([
            // El frontend usa este valor para el aviso de cierre por inactividad.
            'meta' => ['sesion_minutos' => (int) config('session.lifetime')],
        ]);
    }
}
