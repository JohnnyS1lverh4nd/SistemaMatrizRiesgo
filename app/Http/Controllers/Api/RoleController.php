<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoleController extends Controller
{
    /**
     * Roles disponibles para el formulario de usuarios. Los roles son
     * reglas del dominio (seeder), no un catálogo editable.
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        return RoleResource::collection(Role::orderBy('id')->get());
    }
}
