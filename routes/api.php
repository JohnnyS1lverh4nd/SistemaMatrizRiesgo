<?php

use App\Http\Controllers\Api\CurrentUserController;
use App\Http\Controllers\Api\DepartamentoController;
use App\Http\Controllers\Api\EscalaNivelController;
use App\Http\Controllers\Api\FactorRiesgoController;
use App\Http\Controllers\Api\MatrizCalorCeldaController;
use App\Http\Controllers\Api\NivelRiesgoController;
use App\Http\Controllers\Api\PeriodicidadController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', CurrentUserController::class);

    Route::get('/departamentos/{departamento}', [DepartamentoController::class, 'show']);

    Route::apiResource('factores-riesgo', FactorRiesgoController::class);
    Route::apiResource('periodicidades', PeriodicidadController::class);
    Route::apiResource('niveles-riesgo', NivelRiesgoController::class);
    Route::apiResource('escalas-nivel', EscalaNivelController::class);

    Route::apiResource('matriz-calor-celdas', MatrizCalorCeldaController::class)
        ->only(['index', 'show', 'update']);
});