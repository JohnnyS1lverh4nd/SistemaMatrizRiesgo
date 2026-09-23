<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DepartamentoResource;
use App\Models\Departamento;

class DepartamentoController extends Controller
{
    public function show(Departamento $departamento): DepartamentoResource
    {
        $this->authorize('view', $departamento);

        return new DepartamentoResource($departamento);
    }
}