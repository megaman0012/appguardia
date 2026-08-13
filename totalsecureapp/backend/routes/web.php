<?php

use Illuminate\Support\Facades\Route;

Route::get('/admin/login', function () {
    return redirect()->route('acceso.login');
})->name('filament.auth.login');
