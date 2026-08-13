<?php

namespace Modules\MobileApp\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\MobileApp\Services\ExpoNotificationService;

class ExpoServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(ExpoNotificationService::class, function ($app) {
            return new ExpoNotificationService();
        });
    }

    public function boot()
    {
        //
    }
}
