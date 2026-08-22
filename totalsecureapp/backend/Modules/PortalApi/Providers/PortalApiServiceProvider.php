<?php

namespace Modules\PortalApi\Providers;

use Illuminate\Support\ServiceProvider;

class PortalApiServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'PortalApi';
    protected string $moduleNameLower = 'portalapi';

    public function boot()
    {
        $this->registerConfig();
    }

    public function register()
    {
        $this->app->register(RouteServiceProvider::class);
    }

    protected function registerConfig()
    {
        $this->publishes([
            module_path($this->moduleName, 'Config/config.php') => config_path($this->moduleNameLower . '.php'),
        ], 'config');

        $this->mergeConfigFrom(
            module_path($this->moduleName, 'Config/config.php'),
            $this->moduleNameLower
        );
    }

    public function provides()
    {
        return [];
    }
}
