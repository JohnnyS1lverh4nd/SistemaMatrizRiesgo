<?php

use App\Http\Controllers\Api\CurrentUserController;
use App\Http\Controllers\Api\DepartamentoController;
use App\Http\Controllers\Api\EscalaNivelController;
use App\Http\Controllers\Api\FactorRiesgoController;
use App\Http\Controllers\Api\MatrizCalorCeldaController;
use App\Http\Controllers\Api\NivelRiesgoController;
use App\Http\Controllers\Api\PeriodicidadController;
use App\Http\Controllers\Api\ProcesoController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', EnsureUserIsActive::class])->group(function () {
    Route::get('/user', CurrentUserController::class);

    Route::apiResource('users', UserController::class)->except('destroy');
    Route::get('/roles', [RoleController::class, 'index']);

    // Estructura organizativa: sin destroy, se desactiva con activo=false.
    Route::apiResource('departamentos', DepartamentoController::class)->except('destroy');
    Route::apiResource('departamentos.procesos', ProcesoController::class)
        ->shallow()
        ->except('destroy');

    // El nombre del parámetro debe coincidir con el argumento del controlador
    // ($factorRiesgo -> factor_riesgo); si no, Laravel inyecta un modelo vacío.
    // Sin borrado físico: factores y periodicidades se desactivan (activo=false);
    // niveles y escalas son estructura fija de la matriz de calor.
    Route::apiResource('factores-riesgo', FactorRiesgoController::class)
        ->parameters(['factores-riesgo' => 'factor_riesgo'])
        ->except('destroy');
    Route::apiResource('periodicidades', PeriodicidadController::class)
        ->parameters(['periodicidades' => 'periodicidad'])
        ->except('destroy');
    Route::apiResource('niveles-riesgo', NivelRiesgoController::class)
        ->parameters(['niveles-riesgo' => 'nivel_riesgo'])
        ->only(['index', 'show', 'update']);
    Route::apiResource('escalas-nivel', EscalaNivelController::class)
        ->parameters(['escalas-nivel' => 'escala_nivel'])
        ->only(['index', 'show', 'update']);

    Route::apiResource('matriz-calor-celdas', MatrizCalorCeldaController::class)
        ->only(['index', 'show', 'update']);
});
