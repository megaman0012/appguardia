<?php

namespace App\Services\Avisos;

use Illuminate\Support\Facades\Log;

/**
 * Deja el aviso escrito en el log.
 *
 * No reemplaza a un canal real, pero mientras el push no llegue a los teléfonos
 * es la única forma de responder "¿a quién se le avisó y cuándo?" cuando un
 * puesto quedó sin cubrir y alguien pregunta por qué.
 */
class CanalBitacora implements CanalDeAviso
{
    public function nombre(): string
    {
        return 'bitacora';
    }

    public function enviar(int $usuarioId, string $titulo, string $cuerpo, array $datos = []): bool
    {
        Log::channel(config('avisos.canal_log', 'stack'))->info('[aviso] ' . $titulo, [
            'usuario' => $usuarioId,
            'cuerpo'  => $cuerpo,
            'datos'   => $datos,
        ]);

        return true;
    }
}
