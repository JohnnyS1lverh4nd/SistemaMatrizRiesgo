<?php

use App\Http\Controllers\Api\AuditoriaController;
use App\Http\Controllers\Api\CurrentUserController;
use App\Http\Controllers\Api\DepartamentoController;
use App\Http\Controllers\Api\EscalaNivelController;
use App\Http\Controllers\Api\EvidenciaController;
use App\Http\Controllers\Api\ExportacionController;
use App\Http\Controllers\Api\FactorRiesgoController;
use App\Http\Controllers\Api\ImportacionController;
use App\Http\Controllers\Api\MatrizCalorCeldaController;
use App\Http\Controllers\Api\MatrizController;
use App\Http\Controllers\Api\NivelRiesgoController;
use App\Http\Controllers\Api\PeriodicidadController;
use App\Http\Controllers\Api\ProcesoController;
use App\Http\Controllers\Api\ReporteController;
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

    // Carga de ediciones desde Excel (RF-7): vista previa sin escribir y confirmación.
    // Generan o leen archivos: límite más estricto (20 por minuto y usuario).
    Route::middleware('throttle:operaciones-costosas')->group(function () {
        Route::post('/procesos/{proceso}/importacion/vista-previa', [ImportacionController::class, 'vistaPrevia']);
        Route::post('/procesos/{proceso}/importacion', [ImportacionController::class, 'store']);
    });

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

    // Dashboard y reportes: siempre dentro del alcance del usuario.
    Route::get('/dashboard', [ReporteController::class, 'dashboard']);
    Route::get('/reportes/riesgos', [ReporteController::class, 'riesgos']);
    Route::get('/matrices/{matriz}/comparar/{otra}', [ReporteController::class, 'comparar']);
    // Generan o leen archivos: límite más estricto (20 por minuto y usuario).
    Route::middleware('throttle:operaciones-costosas')->group(function () {
        Route::get('/matrices/{matriz}/exportar/pdf', [ExportacionController::class, 'pdf']);
        Route::get('/matrices/{matriz}/exportar/excel', [ExportacionController::class, 'excel']);
    });

    // Auditoría: solo lectura (los registros los crea el sistema).
    Route::get('/auditoria', [AuditoriaController::class, 'index']);
    Route::get('/riesgos/{riesgo}/historial', [AuditoriaController::class, 'riesgo']);
    Route::get('/matrices/{matriz}/historial', [AuditoriaController::class, 'matriz']);

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
