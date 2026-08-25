<?php

namespace App\Providers;

use App\Responses\CustomLogoutResponse;
use Filament\Http\Responses\Auth\Contracts\LogoutResponse as LogoutResponseContract;
use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\LogoutResponse;
use Filament\Navigation\UserMenuItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Stringable;
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
        $this->registrarShimsDeLaravel9();

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

    /**
     * Compatibilidad Filament 2.17 <-> Laravel 8.75.
     *
     * Filament resuelve el registro de cada pagina de edicion con
     * Resource::resolveRecordRouteBinding(), que llama a
     * \$model->resolveRouteBindingQuery(...). Ese metodo se agrego a Eloquent en
     * Laravel 9: en 8.75 el Model solo tiene resolveRouteBinding(), asi que la
     * llamada terminaba en Model::__call y reventaba con
     * "Call to undefined method ...::resolveRouteBindingQuery()".
     * Efecto: TODAS las paginas de edicion del panel respondian 500.
     *
     * Se registra como macro del Builder porque Model::__call reenvia los
     * metodos desconocidos alli, lo que arregla los ~20 modelos de una vez sin
     * tocarlos ni tocar vendor. La implementacion es la misma de Laravel 9.
     *
     * Al subir a Laravel 9+ este shim se puede borrar: el Model ya trae el
     * metodo y el macro dejaria de usarse (Model::__call solo se dispara con
     * metodos que no existen).
     */
    private function registrarShimsDeLaravel9(): void
    {
        if (!method_exists(\Illuminate\Database\Eloquent\Model::class, 'resolveRouteBindingQuery')) {
            Builder::macro('resolveRouteBindingQuery', function ($query, $value, $field = null) {
                return $query->where($field ?? $this->getModel()->getRouteKeyName(), $value);
            });
        }

        // Stringable::toHtmlString() tambien llego en Laravel 9. Filament la usa al
        // renderizar helperText y hint de los formularios:
        //   Str::of($helperText)->markdown()->sanitizeHtml()->toHtmlString()
        // Sin ella, cualquier formulario con helperText responde 500. Se noto al
        // agregar los formularios de Pais, Provincia, Ciudad y Local, que fueron
        // los primeros en usarla. sanitizeHtml() no hace falta: la registra el
        // propio Filament en SupportServiceProvider.
        if (!method_exists(Stringable::class, 'toHtmlString')) {
            Stringable::macro('toHtmlString', function () {
                return new HtmlString($this->value);
            });
        }
    }
}
