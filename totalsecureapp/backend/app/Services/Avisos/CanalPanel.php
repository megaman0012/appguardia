<?php

namespace App\Services\Avisos;

use Filament\Notifications\Notification;
use Modules\Acceso\Models\users;

/**
 * La campanita del panel.
 *
 * Es el canal que faltaba para la pregunta «apreté el botón de pánico y en la
 * web no salió nada». Push y WhatsApp salen del servidor hacia afuera y dependen
 * de Firebase y del gateway; este se queda adentro y **no depende de nada
 * externo**, así que es el único que siempre llega mientras alguien tenga el
 * panel abierto.
 *
 * Escribe en la tabla `notifications`, que Filament lee sola. No hace falta
 * websocket: el panel refresca la campanita por su cuenta (`databaseNotifications
 * PollingInterval`).
 */
class CanalPanel implements CanalDeAviso
{
    public function nombre(): string
    {
        return 'panel';
    }

    public function enviar(int $usuarioId, string $titulo, string $cuerpo, array $datos = []): ResultadoDeAviso
    {
        $usuario = users::find($usuarioId);

        if (!$usuario) {
            return ResultadoDeAviso::omitido('El usuario no existe');
        }

        $notificacion = Notification::make()
            ->title($titulo)
            ->body($cuerpo);

        // Una emergencia tiene que verse distinta de un turno por cubrir.
        $esUrgente = in_array($datos['prioridad'] ?? null, ['alta', 'critica'], true);
        $esUrgente ? $notificacion->danger() : $notificacion->info();

        if (!empty($datos['url'])) {
            /*
             * `Filament\Actions\Action`, no `Filament\Notifications\Actions\Action`.
             *
             * En Filament 3 la accion de una notificacion vivia en su propio
             * namespace; en la 5 se unifico con el resto de las acciones. La
             * clase vieja no existe, y como el canal atrapa sus propias
             * excepciones, el error no se veia: la notificacion simplemente
             * **no aparecia**, y el unico rastro era «Class not found» en la
             * columna `ae_detalle` de `aviso_envio`.
             */
            $notificacion->actions([
                \Filament\Actions\Action::make('ver')
                    ->label('Ver')
                    ->url($datos['url']),
            ]);
        }

        $notificacion->sendToDatabase($usuario);

        return ResultadoDeAviso::enviado('panel');
    }
}
