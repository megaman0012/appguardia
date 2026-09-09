<?php

namespace App\Providers;

use App\Responses\CustomLogoutResponse;
use Filament\Http\Responses\Auth\Contracts\LogoutResponse as LogoutResponseContract;
use Filament\Http\Responses\Auth\LogoutResponse;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        /*
         * `registrarShimsDeLaravel9()` se retiro: eran parches para Laravel
         * 8.75 (`Model::resolveRouteBindingQuery` y `Stringable::toHtmlString`,
         * que llegaron en Laravel 9). Estaban guardados con `method_exists`,
         * asi que desde la subida a Laravel 10 no hacian nada.
         */

        $this->app->singleton(LogoutResponseContract::class, CustomLogoutResponse::class);

        /*
         * ⚠️ Aca vivian tres registros de Filament 2 y **los tres murieron con
         * la subida a Filament 3**:
         *
         *   - `Filament::registerStyles()` dentro de `Filament::serving()`
         *   - `Filament::registerNavigationGroups()`
         *   - `Filament::registerUserMenuItems()`
         *
         * En Filament 3 todo eso se declara en el *panel provider*
         * (`App\Providers\Filament\AdminPanelProvider`). Y no es un detalle
         * cosmetico: `registerNavigationGroups()` no existe en la 3, y como
         * fallaba dentro del `boot()` de un proveedor **se caia la aplicacion
         * completa** -- el panel y tambien la API que usan las tablets, que no
         * tiene nada que ver con Filament. El unico sintoma era un 500 en todo.
         */
    }
}
