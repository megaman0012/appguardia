<?php

namespace App\Http\Controllers;

use App\Services\Avisos\RespuestaWhatsapp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Recibe los mensajes que llegan al WhatsApp de la empresa.
 *
 * Evolution manda acá cada mensaje entrante. Es la otra mitad de la cobertura:
 * el guardia que puede cubrir está franco y no tiene la app —vive en las tablets
 * de los puestos—, así que contesta por WhatsApp y esa respuesta tiene que
 * entrar sola al sistema.
 *
 * Siempre responde 200, salvo que el token no coincida. Si devolviera error,
 * Evolution reintentaría el mismo mensaje una y otra vez.
 */
class WhatsappWebhookController extends Controller
{
    public function __construct(private RespuestaWhatsapp $respuestas)
    {
    }

    public function recibir(Request $request, string $token): JsonResponse
    {
        $esperado = (string) config('avisos.whatsapp.webhook_token');

        // Sin token configurado el webhook no existe: es una puerta abierta a
        // que cualquiera simule respuestas de guardias.
        if ($esperado === '' || !hash_equals($esperado, $token)) {
            return response()->json(['ok' => false], 404);
        }

        $datos = $request->input('data', []);

        if ($request->input('event') && $request->input('event') !== 'messages.upsert') {
            return response()->json(['ok' => true, 'ignorado' => 'otro evento']);
        }

        // Los mensajes que manda el propio sistema vuelven por el webhook: sin
        // esto, contestaría a sus propias respuestas.
        if (data_get($datos, 'key.fromMe')) {
            return response()->json(['ok' => true, 'ignorado' => 'propio']);
        }

        $jid = (string) data_get($datos, 'key.remoteJid', '');

        // Los grupos no se atienden: una convocatoria es uno a uno.
        if ($jid === '' || str_contains($jid, '@g.us')) {
            return response()->json(['ok' => true, 'ignorado' => 'grupo o sin origen']);
        }

        $texto = (string) (data_get($datos, 'message.conversation')
            ?? data_get($datos, 'message.extendedTextMessage.text')
            ?? '');

        if (trim($texto) === '') {
            return response()->json(['ok' => true, 'ignorado' => 'sin texto']);
        }

        $numero = explode('@', $jid)[0];

        try {
            $r = $this->respuestas->procesar($numero, $texto);
        } catch (\Throwable $e) {
            // Un fallo procesando un mensaje no puede hacer que Evolution
            // reintente en bucle.
            report($e);

            return response()->json(['ok' => true, 'error' => 'procesado con error']);
        }

        Log::info('[whatsapp] respuesta de ' . $numero, $r);

        return response()->json(['ok' => true] + $r);
    }
}
