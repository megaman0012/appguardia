<?php

namespace App\Providers;

use App\Responses\CustomLogoutResponse;
use Filament\Http\Responses\Auth\Contracts\LogoutResponse as LogoutResponseContract;
use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\LogoutResponse;
use Filament\Navigation\UserMenuItem;
use Illuminate\Support\HtmlString;
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
        $this->app->singleton(LogoutResponseContract::class, CustomLogoutResponse::class);

        Filament::serving(function () {
            Filament::registerStyles([
                asset('css/filament-styles.css'),
            ]);
        });

        Filament::serving(function () {
            Filament::registerUserMenuItems([
                'mi-opcion' => UserMenuItem::make()
                    ->label('Seleccionar Perfil')
                    ->url(route('acceso.perfil'))
                    ->icon('heroicon-o-link'),
            ]);
        });

    }
}
