<?php

namespace App\Services\Avisos;

use Modules\MobileApp\Services\ExpoNotificationService;

/**
 * Notificación push de la app (Expo).
 *
 * OJO: sin `google-services.json` y las credenciales de FCM, Expo acepta el
 * envío pero la notificación **no llega a un teléfono Android real**. Por eso el
 * aviso nunca es el único camino: la pantalla "Turnos disponibles" muestra lo
 * mismo sin depender de esto.
 */
class CanalPush implements CanalDeAviso
{
    public function __construct(private ExpoNotificationService $expo)
    {
    }

    public function nombre(): string
    {
        return 'push';
    }

    public function enviar(int $usuarioId, string $titulo, string $cuerpo, array $datos = []): bool
    {
        $r = $this->expo->sendToUser($usuarioId, $titulo, $cuerpo, $datos);

        return (bool) ($r['success'] ?? false);
    }
}
