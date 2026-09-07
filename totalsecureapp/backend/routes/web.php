<?php

use Illuminate\Support\Facades\Route;

// La raiz no servia nada: un 404 de Laravel. El sistema no tiene portada
// publica, y quien abre http://<host>:3031 a secas no tiene como adivinar
// que la entrada es /acceso/login. Se manda ahi, igual que /admin/login.
Route::get('/', function () {
    return redirect()->route('acceso.login');
});

Route::get('/admin/login', function () {
    return redirect()->route('acceso.login');
})->name('filament.auth.login');
