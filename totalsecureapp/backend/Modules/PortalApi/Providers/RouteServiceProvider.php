<?php

namespace Modules\PortalApi\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected $moduleNamespace = 'Modules\PortalApi\Http\Controllers';

    public function map()
    {
        $this->mapApiRoutes();
    }

    /**
     * Prefijo propio (api/portal) para que el portal cliente quede separado de la
     * API de la app movil y del panel, aunque comparta dominio y modelos.
     */
    protected function mapApiRoutes()
    {
        Route::prefix('api/portal')
            ->middleware('api')
            ->namespace($this->moduleNamespace)
            ->group(module_path('PortalApi', '/Routes/api.php'));
    }
}
