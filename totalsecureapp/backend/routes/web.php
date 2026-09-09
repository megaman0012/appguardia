<?php

use Illuminate\Support\Facades\Route;

// La raiz no servia nada: un 404 de Laravel. El sistema no tiene portada
// publica, y quien abre http://<host>:3031 a secas no tiene como adivinar
// que la entrada es /acceso/login. Se manda ahi, igual que /admin/login.
Route::get('/', function () {
    return redirect()->route('acceso.login');
});

/*
 * El panel de Filament **no usa la pantalla de ingreso de Filament**: quien
 * entra lo hace por `/acceso/login`, la del modulo Acceso, con su cedula y su
 * seleccion de perfil. Esta ruta existe solo para que el panel tenga a donde
 * mandar a un visitante sin sesion.
 *
 * ⚠️ **El nombre cambio con Filament 3.** La 2 lo tomaba de
 * `config/filament.php` (`auth.route`) y podia llamarse `filament.auth.login`;
 * la 3 lo construye a partir del id del panel y espera
 * **`filament.admin.auth.login`**. Con el nombre viejo, cualquier pagina del
 * panel moria con «Route [filament.admin.auth.login] not defined»: `/admin`
 * devolvia 500.
 */
Route::get('/admin/login', function () {
    return redirect()->route('acceso.login');
})->name('filament.admin.auth.login');
