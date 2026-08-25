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
use Modules\MobileApp\Http\Controllers\VacanteController;
use Modules\MobileApp\Http\Controllers\PerfilController;

Route::post('login', [LoginController::class, 'login']);
Route::post('solicitud_paswchg', [LoginController::class, 'solicitud_cambiopass']);
Route::post('procesar_paswchg', [LoginController::class, 'procesar_cambiopass']);

// Perfiles del usuario autenticado (RBAC)
Route::middleware('api.auth')->group(function () {
    Route::post('seleccionar_perfil', [PerfilController::class, 'seleccionar_perfil']);
    Route::post('procesar_perfil', [PerfilController::class, 'procesar_perfil']);
});

Route::middleware('api.auth')->group(function () {

    Route::post('instituciones', [InstitucionController::class, 'allInstitucions'])
        ->middleware('permission.api:instituciones.seleccionar');
    Route::post('biometria', [BiometriaController::class, 'biometria'])
        ->middleware('permission.api:biometria.marcar');

    Route::post('acceso', [AccesoController::class, 'acceso'])
        ->middleware('permission.api:acceso.registrar');
    Route::post('accesosbyinst', [AccesoController::class, 'getAccesosByInst'])
        ->middleware('permission.api:acceso.ver');
    Route::post('accesout', [AccesoController::class, 'accesOut'])
        ->middleware('permission.api:acceso.registrar');

    // Pre-registro de visitantes
    Route::post('acceso/preregistro', [AccesoController::class, 'preregistro'])
        ->middleware('permission.api:acceso.registrar_visitante');
    Route::post('acceso/preregistros', [AccesoController::class, 'listPreregistros'])
        ->middleware('permission.api:acceso.ver');
    Route::post('acceso/cancelar-preregistro', [AccesoController::class, 'cancelarPreregistro'])
        ->middleware('permission.api:acceso.registrar_visitante');

    Route::post('rondas', [RondaController::class, 'allRonda'])
        ->middleware('permission.api:rondas.ver');
    Route::post('rondas_gestion', [RondaController::class, 'ronda_gestion'])
        ->middleware('permission.api:rondas.gestionar');
    Route::post('rondas_detalle', [RondaController::class, 'detalle_by_id_ronda'])
        ->middleware('permission.api:rondas.ver_detalle');
    Route::post('rondas_detalle_gestion', [RondaController::class, 'detalle_gestion'])
        ->middleware('permission.api:rondas.gestionar');
    Route::post('rondas_detalle_qrcode', [RondaController::class, 'detalle_qrcode'])
        ->middleware('permission.api:rondas.scannear_qr');

    Route::post('novedad_create', [NovedadController::class, 'create'])
        ->middleware('permission.api:novedades.crear');
    Route::post('novedad_listbydate', [NovedadController::class, 'listByDate'])
        ->middleware('permission.api:novedades.ver');

    Route::post('/token/save', [NotificacionController::class, 'saveToken'])
        ->middleware('permission.api:notificaciones.registrar');
    Route::post('/token/remove', [NotificacionController::class, 'removeToken'])
        ->middleware('permission.api:notificaciones.registrar');

    Route::post('/alert/today', [AlertaController::class, 'today'])
        ->middleware('permission.api:alertas.ver');
    Route::post('/alert/crear', [AlertaController::class, 'crear'])
        ->middleware('permission.api:alertas.crear');
    Route::post('/alert/{id}/atender', [AlertaController::class, 'atender'])
        ->middleware('permission.api:alertas.atender');
    Route::post('/alert/{id}/cancelar', [AlertaController::class, 'cancelar'])
        ->middleware('permission.api:alertas.atender');
    Route::post('/alert/estadisticas', [AlertaController::class, 'estadisticas'])
        ->middleware('permission.api:alertas.ver_estadisticas');
    Route::get('/alert/{id}/historial', [AlertaController::class, 'historial'])
        ->middleware('permission.api:alertas.ver_historial');

    Route::post('/notification/institution', [NotificacionController::class, 'sendToInstitution'])
        ->middleware('permission.api:notificaciones.registrar');
    Route::post('/notification/user', [NotificacionController::class, 'sendToUser'])
        ->middleware('permission.api:notificaciones.registrar');
    Route::post('/notification/bulk', [NotificacionController::class, 'sendBulk'])
        ->middleware('permission.api:notificaciones.registrar');

    Route::post('/inventario/listbyinst', [InventarioController::class, 'allListByInst'])
        ->middleware('permission.api:inventario.ver');
    Route::post('/inventario/listsave', [InventarioController::class, 'saveListMov'])
        ->middleware('permission.api:inventario.registrar');
    Route::post('/inventario/finishsave', [InventarioController::class, 'finishListMov'])
        ->middleware('permission.api:inventario.finalizar');
    Route::post('/inventario/registrar-baja', [InventarioController::class, 'registrarBaja'])
        ->middleware('permission.api:inventario.registrar');

    // === COBERTURA DE TURNOS ===
    Route::post('/vacantes-disponibles', [VacanteController::class, 'disponibles'])
        ->middleware('permission.api:vacantes.ver');
    Route::post('/vacantes-mis-postulaciones', [VacanteController::class, 'misPostulaciones'])
        ->middleware('permission.api:vacantes.ver');
    Route::post('/vacantes-postular', [VacanteController::class, 'postular'])
        ->middleware('permission.api:vacantes.postular');
    Route::post('/vacantes-retirar', [VacanteController::class, 'retirar'])
        ->middleware('permission.api:vacantes.postular');
    Route::post('/turnos-proximos', [VacanteController::class, 'proximos'])
        ->middleware('permission.api:vacantes.avisar_ausencia');
    Route::post('/turnos-avisar-ausencia', [VacanteController::class, 'avisarAusencia'])
        ->middleware('permission.api:vacantes.avisar_ausencia');
    Route::post('/perfil-extras', [VacanteController::class, 'aceptarExtras'])
        ->middleware('permission.api:perfil.editar');

    // === TURNOS === (sin permiso granular: funcionalidad base de todo guardia)
    Route::post('/turnos-del-dia', [TurnoController::class, 'turnosDelDia']);
    Route::post('/turnos-vincular-marcaje', [TurnoController::class, 'vincularMarcaje']);
    Route::post('/turnos-cumplimiento', [TurnoController::class, 'cumplimiento']);

});


