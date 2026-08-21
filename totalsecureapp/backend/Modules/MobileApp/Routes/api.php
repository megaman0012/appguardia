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
use Modules\MobileApp\Http\Controllers\TurnoController;

Route::post('login', [LoginController::class, 'login']);
Route::post('solicitud_paswchg', [LoginController::class, 'solicitud_cambiopass']);
Route::post('procesar_paswchg', [LoginController::class, 'procesar_cambiopass']);

Route::middleware('api.auth')->group(function () {

    Route::post('instituciones', [InstitucionController::class, 'allInstitucions']);
    Route::post('biometria', [BiometriaController::class, 'biometria']);

    Route::post('acceso', [AccesoController::class, 'acceso']);
    Route::post('accesosbyinst', [AccesoController::class, 'getAccesosByInst']);
    Route::post('accesout', [AccesoController::class, 'accesOut']);

    // Pre-registro de visitantes
    Route::post('acceso/preregistro', [AccesoController::class, 'preregistro']);
    Route::post('acceso/preregistros', [AccesoController::class, 'listPreregistros']);
    Route::post('acceso/cancelar-preregistro', [AccesoController::class, 'cancelarPreregistro']);

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
    Route::post('/alert/crear', [AlertaController::class, 'crear']);
    Route::post('/alert/{id}/atender', [AlertaController::class, 'atender']);
    Route::post('/alert/{id}/cancelar', [AlertaController::class, 'cancelar']);
    Route::post('/alert/estadisticas', [AlertaController::class, 'estadisticas']);
    Route::get('/alert/{id}/historial', [AlertaController::class, 'historial']);

    Route::post('/notification/institution', [NotificacionController::class, 'sendToInstitution']);
    Route::post('/notification/user', [NotificacionController::class, 'sendToUser']);
    Route::post('/notification/bulk', [NotificacionController::class, 'sendBulk']);

    Route::post('/inventario/listbyinst', [InventarioController::class, 'allListByInst']);
    Route::post('/inventario/listsave', [InventarioController::class, 'saveListMov']);
    Route::post('/inventario/finishsave', [InventarioController::class, 'finishListMov']);
    Route::post('/inventario/registrar-baja', [InventarioController::class, 'registrarBaja']);

    // === TURNOS ===
    Route::post('/turnos-del-dia', [TurnoController::class, 'turnosDelDia']);
    Route::post('/turnos-vincular-marcaje', [TurnoController::class, 'vincularMarcaje']);
    Route::post('/turnos-cumplimiento', [TurnoController::class, 'cumplimiento']);

});


