<?php

namespace App\Services\Avisos;

use Modules\MobileApp\Models\users;

/**
 * Aviso por WhatsApp, a través del gateway Evolution.
 *
 * Dos filtros antes de intentar nada, y los dos son deliberados:
 *
 *  - **Consentimiento explícito.** `usu_acepta_whatsapp` es una casilla aparte
 *    de "quiero turnos extra": aceptar trabajar de más no es aceptar que le
 *    escriban al teléfono personal.
 *  - **Número válido.** Un número mal formado no da error: el gateway lo acepta
 *    y el mensaje se pierde. Mejor registrarlo como omitido que creer que salió.
 */
class CanalWhatsApp implements CanalDeAviso
{
    public function __construct(private EvolutionApi $api)
    {
    }

    public function nombre(): string
    {
        return 'whatsapp';
    }

    public function enviar(int $usuarioId, string $titulo, string $cuerpo, array $datos = []): ResultadoDeAviso
    {
        if (!$this->api->configurado()) {
            return ResultadoDeAviso::omitido('WhatsApp no está configurado');
        }

        $usuario = users::find($usuarioId);

        if (!$usuario) {
            return ResultadoDeAviso::omitido('El usuario no existe');
        }

        if (!$usuario->usu_acepta_whatsapp) {
            return ResultadoDeAviso::omitido('No autorizó recibir avisos por WhatsApp');
        }

        $numero = NumeroWhatsapp::normalizar($usuario->usu_whatsapp);

        if (!NumeroWhatsapp::valido($numero)) {
            return ResultadoDeAviso::omitido('Sin número de WhatsApp válido', $numero);
        }

        $r = $this->api->enviarTexto($numero, sprintf("*%s*\n%s", $titulo, $cuerpo));

        return $r['ok']
            ? ResultadoDeAviso::enviado($numero)
            : ResultadoDeAviso::fallido($r['detalle'] ?? 'Sin detalle', $numero);
    }

    /** Para que el registro pueda decir a qué número salió (o iba a salir). */
    public function destinoDe(int $usuarioId): ?string
    {
        $usuario = users::find($usuarioId);

        return $usuario ? NumeroWhatsapp::normalizar($usuario->usu_whatsapp) : null;
    }
}
