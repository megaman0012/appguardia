<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
use Filament\Facades\Filament;

Route::prefix('acceso')->middleware(['web'])->group(function() {
    Route::get( 'login'             , 'LoginController@index'              )->name('acceso.login');
    Route::post('login_check'       , 'LoginController@login_check'   );
    Route::get( 'seleccionar_perfil', 'LoginController@seleccionar_perfil' )->name('acceso.perfil');
    Route::post('procesar_perfil'   , 'LoginController@procesar_perfil'    );
    Route::get( 'logout'            , 'LoginController@logout_check'       );
    Route::post('solicitud_cambiopass', 'LoginController@solicitud_cambiopass');
    Route::get('cambiar_password/{numero}', 'LoginController@cambiar_password');
    Route::post('procesar_cambiopass', 'LoginController@procesar_cambiopass');
});
