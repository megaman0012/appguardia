<?php

use Illuminate\Support\Facades\Route;
use Modules\PortalApi\Http\Controllers\InstitucionController;
use Modules\PortalApi\Http\Controllers\ReporteController;
use Modules\PortalApi\Http\Controllers\ResumenController;

/**
 * API del portal cliente (Fase 8). Prefijo api/portal.
 *
 * Solo GET: es una capa de lectura sobre el mismo dominio que consume la app
 * movil. Cada ruta exige su permiso portal.* (rol Cliente), asi que un token de
 * la app movil no puede leer aqui, ni uno del portal escribir alla.
 */
Route::middleware('api.auth')->group(function () {

    Route::get('instituciones', [InstitucionController::class, 'index'])
        ->middleware('permission.api:portal.instituciones');

    Route::get('resumen', [ResumenController::class, 'index'])
        ->middleware('permission.api:portal.resumen');

    Route::get('biometria', [ReporteController::class, 'biometria'])
        ->middleware('permission.api:portal.biometria');

    Route::get('rondas', [ReporteController::class, 'rondas'])
        ->middleware('permission.api:portal.rondas');

    Route::get('novedades', [ReporteController::class, 'novedades'])
        ->middleware('permission.api:portal.novedades');

    Route::get('accesos', [ReporteController::class, 'accesos'])
        ->middleware('permission.api:portal.accesos');

    Route::get('alertas', [ReporteController::class, 'alertas'])
        ->middleware('permission.api:portal.alertas');

});
