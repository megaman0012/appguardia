<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use App\Events\AlertaCreada;
use App\Listeners\AvisarAlertaCreada;
use App\Observers\AlertaObserver;
use App\Observers\TurnoObserver;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Modules\Administracion\Models\Alertas;
use Modules\Administracion\Models\Turno;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        /*
         * El boton de EMERGENCIA de los guardias.
         *
         * `AlertaCreada` se emitia desde el principio y **no lo escuchaba
         * nadie**: al ser un `ShouldBroadcast` con `BROADCAST_DRIVER=log`, cada
         * pedido de auxilio terminaba como una linea en `storage/logs`. Este
         * listener es lo que convierte la alerta en un aviso a una persona.
         */
        AlertaCreada::class => [
            AvisarAlertaCreada::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        // Invalidacion por evento del cache del dashboard (Fase 9).
        Alertas::observe(AlertaObserver::class);
        Turno::observe(TurnoObserver::class);
    }
}
