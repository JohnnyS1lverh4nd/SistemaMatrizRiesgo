<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
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
            $user->departamentos()->sync($request->validated('departamento_ids', []));

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
                $user->departamentos()->sync($request->validated('departamento_ids'));
            }
        });

        return new UserResource($user->load(self::RELACIONES));
    }
}
