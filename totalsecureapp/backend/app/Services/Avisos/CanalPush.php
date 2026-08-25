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

    public function enviar(int $usuarioId, string $titulo, string $cuerpo, array $datos = []): ResultadoDeAviso
    {
        $r = $this->expo->sendToUser($usuarioId, $titulo, $cuerpo, $datos);

        if ($r['success'] ?? false) {
            return ResultadoDeAviso::enviado();
        }

        $motivo = $r['message'] ?? 'Sin detalle';

        // Un guardia que nunca abrió la app no tiene token: no es una falla del
        // sistema, es que no hay a dónde mandarlo.
        return str_contains($motivo, 'tokens')
            ? ResultadoDeAviso::omitido('El usuario no tiene la app registrada')
            : ResultadoDeAviso::fallido($motivo);
    }
}
