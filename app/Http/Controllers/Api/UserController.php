<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    private const RELACIONES = ['role', 'departamentos'];

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $users = User::with(self::RELACIONES)
            ->orderBy('name')
            ->paginate(25);

        return UserResource::collection($users);
    }

    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        return new UserResource($user->load(self::RELACIONES));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        // Usuario y departamentos se guardan juntos o no se guarda nada.
        $user = DB::transaction(function () use ($request) {
            $user = User::create($request->safe()->except('departamento_ids'));
            $this->sincronizarDepartamentos($user, $request->validated('departamento_ids', []));

            return $user;
        });

        return (new UserResource($user->load(self::RELACIONES)))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        DB::transaction(function () use ($request, $user) {
            $user->update($request->safe()->except('departamento_ids'));

            if ($request->has('departamento_ids')) {
                $this->sincronizarDepartamentos($user, $request->validated('departamento_ids'));
            }
        });

        return new UserResource($user->load(self::RELACIONES));
    }

    /**
     * Los departamentos deciden qué puede ver y editar el usuario: el cambio
     * se audita aquí porque la tabla pivote no dispara eventos de modelo.
     *
     * @param  array<int, int>  $departamentoIds
     */
    private function sincronizarDepartamentos(User $user, array $departamentoIds): void
    {
        $antes = $user->departamentos()->pluck('departamentos.id')->map(fn ($id) => (int) $id)->all();
        $user->departamentos()->sync($departamentoIds);
        $user->unsetRelation('departamentos');

        Auditor::registrarRelacion('usuario', $user->id, 'departamento_ids', $antes, array_map('intval', $departamentoIds));
    }
}
