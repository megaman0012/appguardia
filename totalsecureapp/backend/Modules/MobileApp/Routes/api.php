<?php

use Illuminate\Support\Facades\Route;
use Modules\MobileApp\Http\Controllers\InventarioController;
use Modules\MobileApp\Http\Controllers\NovedadController;
use Modules\MobileApp\Http\Controllers\LoginController;
use Modules\MobileApp\Http\Controllers\BiometriaController;
use Modules\MobileApp\Http\Controllers\InstitucionController;
use Modules\MobileApp\Http\Controllers\AccesoController;
use Modules\MobileApp\Http\Controllers\RondaController;
use Modules\MobileApp\Http\Controllers\NotificacionController;
use Modules\MobileApp\Http\Controllers\AlertaController;

Route::post('login', [LoginController::class, 'login']);
Route::post('solicitud_paswchg', [LoginController::class, 'solicitud_cambiopass']);
Route::post('procesar_paswchg', [LoginController::class, 'procesar_cambiopass']);

Route::middleware('api.auth')->group(function () {

    Route::post('instituciones', [InstitucionController::class, 'allInstitucions']);
    Route::post('biometria', [BiometriaController::class, 'biometria']);

    Route::post('acceso', [AccesoController::class, 'acceso']);
    Route::post('accesosbyinst', [AccesoController::class, 'getAccesosByInst']);
    Route::post('accesout', [AccesoController::class, 'accesOut']);

    Route::post('rondas', [RondaController::class, 'allRonda']);
    Route::post('rondas_gestion', [RondaController::class, 'ronda_gestion']);
    Route::post('rondas_detalle', [RondaController::class, 'detalle_by_id_ronda']);
    Route::post('rondas_detalle_gestion', [RondaController::class, 'detalle_gestion']);
    Route::post('rondas_detalle_qrcode', [RondaController::class, 'detalle_qrcode']);

    Route::post('novedad_create', [NovedadController::class, 'create']);
    Route::post('novedad_listbydate', [NovedadController::class, 'listByDate']);

    Route::post('/token/save', [NotificacionController::class, 'saveToken']);
    Route::post('/token/remove', [NotificacionController::class, 'removeToken']);

    Route::post('/alert/today', [AlertaController::class, 'today']);

    Route::post('/notification/institution', [NotificacionController::class, 'sendToInstitution']);
    Route::post('/notification/user', [NotificacionController::class, 'sendToUser']);
    Route::post('/notification/bulk', [NotificacionController::class, 'sendBulk']);

    Route::post('/inventario/listbyinst', [InventarioController::class, 'allListByInst']);
    Route::post('/inventario/listsave', [InventarioController::class, 'saveListMov']);
    Route::post('/inventario/finishsave', [InventarioController::class, 'finishListMov']);



});


