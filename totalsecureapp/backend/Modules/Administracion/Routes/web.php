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

Route::prefix('administracion')->middleware(['web'])->group(function() {
    Route::get('persona.index', 'PersonaController@index')->middleware('permission:administracion/persona.index');
    Route::get('persona.datatable', 'PersonaController@datatable');
    Route::post('persona.store', 'PersonaController@store');
    Route::post('persona.update', 'PersonaController@update');

    Route::get( 'qrcode.point/{id}', 'QrCodeController@MarkerPointControl' )->name('qrcode.point');

});
