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

    Route::get( 'qrcode.point/{id}', 'QrCodeController@MarkerPointControl' )->name('qrcode.point');

});
