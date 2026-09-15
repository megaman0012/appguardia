<?php

namespace App\Listeners;

use App\Events\AlertaCreada;
use App\Services\NotificadorAlerta;

/**
 * Convierte una alerta guardada en un aviso que le llega a una persona.
 *
 * El evento `AlertaCreada` ya se emitía; lo que no había era nadie escuchándolo.
 * Como es un `ShouldBroadcast` y el driver de broadcasting es `log`, el sistema
 * dejaba constancia de la emergencia en un archivo y no despertaba a nadie.
 *
 * Es síncrono a propósito, aunque la cola sea `sync`: una alerta de pánico que
 * espera a que alguien procese una cola no es una alerta. El costo es que el
 * guardia espera lo que tarde el gateway de WhatsApp (8 s de timeout) antes de
 * ver «Alerta enviada» en la tablet. Cuando haya un worker de verdad
 * (`QUEUE_CONNECTION=redis`), esto debería implementar `ShouldQueue` con una
 * cola de alta prioridad, **no antes**: con `sync` un `ShouldQueue` se ejecuta
 * igual en línea y sólo agrega indirección.
 */
class AvisarAlertaCreada
{
    public function __construct(private NotificadorAlerta $notificador)
    {
    }

    public function handle(AlertaCreada $evento): void
    {
        try {
            $this->notificador->alertaCreada($evento->alerta);
        } catch (\Throwable $e) {
            // La alerta ya está creada y visible en «Alertas de hoy». Que el
            // aviso falle no puede hacer fallar el pedido de auxilio.
            report($e);
        }
    }
}
