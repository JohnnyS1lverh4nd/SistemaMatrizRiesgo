<?php

use App\Http\Controllers\Api\CurrentUserController;
use App\Http\Controllers\Api\DepartamentoController;
use App\Http\Controllers\Api\EscalaNivelController;
use App\Http\Controllers\Api\EvidenciaController;
use App\Http\Controllers\Api\FactorRiesgoController;
use App\Http\Controllers\Api\MatrizCalorCeldaController;
use App\Http\Controllers\Api\MatrizController;
use App\Http\Controllers\Api\NivelRiesgoController;
use App\Http\Controllers\Api\PeriodicidadController;
use App\Http\Controllers\Api\ProcesoController;
use App\Http\Controllers\Api\RiesgoController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SeguimientoController;
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

    // Ediciones de matriz. destroy solo descarta borradores (soft delete).
    Route::apiResource('procesos.matrices', MatrizController::class)
        ->shallow()
        ->parameters(['matrices' => 'matriz']);
    Route::post('/matrices/{matriz}/publicar', [MatrizController::class, 'publicar']);
    Route::post('/matrices/{matriz}/archivar', [MatrizController::class, 'archivar']);
    Route::post('/matrices/{matriz}/clonar', [MatrizController::class, 'clonar']);

    // Riesgos de una edición. destroy = eliminación lógica, solo en borradores.
    Route::apiResource('matrices.riesgos', RiesgoController::class)
        ->shallow()
        ->parameters(['matrices' => 'matriz']);

    // Seguimiento: histórico sin update ni destroy (un error se anula con motivo).
    Route::apiResource('riesgos.seguimientos', SeguimientoController::class)
        ->shallow()
        ->only(['index', 'store', 'show']);
    Route::post('/seguimientos/{seguimiento}/anular', [SeguimientoController::class, 'anular']);
    Route::get('/evidencias/{evidencia}/descargar', [EvidenciaController::class, 'descargar']);

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
