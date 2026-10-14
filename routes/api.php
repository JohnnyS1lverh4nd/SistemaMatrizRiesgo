<?php

use App\Http\Controllers\Api\CurrentUserController;
use App\Http\Controllers\Api\DepartamentoController;
use App\Http\Controllers\Api\EscalaNivelController;
use App\Http\Controllers\Api\FactorRiesgoController;
use App\Http\Controllers\Api\MatrizCalorCeldaController;
use App\Http\Controllers\Api\NivelRiesgoController;
use App\Http\Controllers\Api\PeriodicidadController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', EnsureUserIsActive::class])->group(function () {
    Route::get('/user', CurrentUserController::class);

    Route::apiResource('users', UserController::class)->except('destroy');
    Route::get('/roles', [RoleController::class, 'index']);

    Route::get('/departamentos', [DepartamentoController::class, 'index']);
    Route::get('/departamentos/{departamento}', [DepartamentoController::class, 'show']);

    Route::apiResource('factores-riesgo', FactorRiesgoController::class);
    Route::apiResource('periodicidades', PeriodicidadController::class);
    Route::apiResource('niveles-riesgo', NivelRiesgoController::class);
    Route::apiResource('escalas-nivel', EscalaNivelController::class);

    Route::apiResource('matriz-calor-celdas', MatrizCalorCeldaController::class)
        ->only(['index', 'show', 'update']);
});
