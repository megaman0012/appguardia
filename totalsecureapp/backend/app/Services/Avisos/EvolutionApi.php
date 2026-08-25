<?php

namespace App\Services\Avisos;

use Illuminate\Support\Facades\Http;

/**
 * Cliente del gateway Evolution API.
 *
 * Evolution corre APARTE del proyecto (su propio contenedor) y mantiene la
 * sesión de WhatsApp como dispositivo enlazado. Acá solo se le habla por HTTP:
 * si mañana se cambia por otro gateway, se reemplaza esta clase y nada más.
 *
 * Es un cliente NO oficial de WhatsApp: el número puede ser bloqueado sin aviso.
 * Por eso ningún camino del sistema depende de que esto funcione — la pantalla
 * "Turnos disponibles" muestra lo mismo sin WhatsApp.
 */
class EvolutionApi
{
    public function __construct(
        private ?string $url = null,
        private ?string $instancia = null,
        private ?string $apiKey = null,
        private int $timeout = 8
    ) {
        $this->url       = rtrim($url ?? (string) config('avisos.whatsapp.url'), '/');
        $this->instancia = $instancia ?? (string) config('avisos.whatsapp.instancia');
        $this->apiKey    = $apiKey ?? (string) config('avisos.whatsapp.api_key');
        $this->timeout   = (int) config('avisos.whatsapp.timeout', $this->timeout);
    }

    /** Si hay con qué hablarle al gateway. Sin esto no se intenta nada. */
    public function configurado(): bool
    {
        return $this->url !== '' && $this->instancia !== '' && $this->apiKey !== '';
    }

    /**
     * Estado de la sesión de WhatsApp.
     *
     * `abierta` = enlazada y funcionando. `conectando` = esperando el QR.
     * `cerrada` = hay que volver a escanear. Es lo que el panel muestra: una
     * sesión caída no avisa de nada y nadie se entera hasta que un puesto queda
     * vacío.
     *
     * @return array{configurado: bool, estado: string, numero: ?string, detalle: ?string}
     */
    public function estado(): array
    {
        if (!$this->configurado()) {
            return [
                'configurado' => false,
                'estado'      => 'sin_configurar',
                'numero'      => null,
                'detalle'     => 'Falta completar WHATSAPP_URL, WHATSAPP_INSTANCIA y WHATSAPP_API_KEY en el .env',
            ];
        }

        try {
            $r = $this->cliente()->get("{$this->url}/instance/connectionState/{$this->instancia}");

            if (!$r->successful()) {
                return $this->estadoDeError('El gateway respondió ' . $r->status());
            }

            $estado = $r->json('instance.state') ?? $r->json('state') ?? 'desconocido';

            return [
                'configurado' => true,
                'estado'      => $this->traducirEstado($estado),
                'numero'      => $this->numeroConectado(),
                'detalle'     => null,
            ];
        } catch (\Throwable $e) {
            // El gateway apagado es el caso más común y no es un error del
            // sistema: se informa, no se propaga.
            return $this->estadoDeError($e->getMessage());
        }
    }

    /**
     * Manda un mensaje de texto.
     *
     * @return array{ok: bool, detalle: ?string}
     */
    public function enviarTexto(string $numero, string $texto): array
    {
        if (!$this->configurado()) {
            return ['ok' => false, 'detalle' => 'WhatsApp no está configurado'];
        }

        try {
            $r = $this->cliente()->post("{$this->url}/message/sendText/{$this->instancia}", [
                'number' => $numero,
                'text'   => $texto,
            ]);

            if ($r->successful()) {
                return ['ok' => true, 'detalle' => null];
            }

            return ['ok' => false, 'detalle' => 'HTTP ' . $r->status() . ': ' . mb_substr($r->body(), 0, 200)];
        } catch (\Throwable $e) {
            return ['ok' => false, 'detalle' => $e->getMessage()];
        }
    }

    /** El número con el que quedó enlazada la sesión, para mostrarlo en el panel. */
    private function numeroConectado(): ?string
    {
        try {
            $r = $this->cliente()->get("{$this->url}/instance/fetchInstances", [
                'instanceName' => $this->instancia,
            ]);

            if (!$r->successful()) {
                return null;
            }

            $datos = $r->json();
            $primera = $datos[0] ?? $datos;

            // El nombre del campo cambió entre versiones de Evolution; se prueban
            // los conocidos antes de darse por vencido.
            $jid = data_get($primera, 'ownerJid')
                ?? data_get($primera, 'instance.owner')
                ?? data_get($primera, 'owner');

            return $jid ? explode('@', (string) $jid)[0] : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function cliente()
    {
        return Http::timeout($this->timeout)
            ->withHeaders(['apikey' => $this->apiKey])
            ->acceptJson();
    }

    private function traducirEstado(string $estado): string
    {
        return match ($estado) {
            'open'       => 'abierta',
            'connecting' => 'conectando',
            'close'      => 'cerrada',
            default      => $estado,
        };
    }

    /** @return array{configurado: bool, estado: string, numero: ?string, detalle: ?string} */
    private function estadoDeError(string $detalle): array
    {
        return [
            'configurado' => true,
            'estado'      => 'sin_respuesta',
            'numero'      => null,
            'detalle'     => $detalle,
        ];
    }
}
