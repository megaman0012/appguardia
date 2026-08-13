<?php

namespace App\Providers;

use App\Services\CorsService;
use Illuminate\Support\ServiceProvider;

class CorsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(CorsService::class, function ($app) {
            return new CorsService($app['config']->get('cors', []));
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}