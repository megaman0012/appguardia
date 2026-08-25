<?php

namespace App\Filament\Widgets;

use App\Services\Avisos\EvolutionApi;
use Filament\Widgets\Widget;
use Modules\Administracion\Models\AvisoEnvio;

/**
 * Estado del canal de WhatsApp, arriba del registro de avisos.
 *
 * Existe por un motivo operativo: la sesión de WhatsApp se cae sola cada tanto
 * y hay que volver a escanear el QR. Si nadie lo ve, los avisos dejan de salir
 * en silencio y el problema se descubre cuando un puesto amanece vacío.
 */
class EstadoWhatsapp extends Widget
{
    protected static string $view = 'filament.widgets.estado-whatsapp';
    protected int | string | array $columnSpan = 'full';

    /** @return array<string, mixed> */
    public function getDatos(): array
    {
        $estado = app(EvolutionApi::class)->estado();

        $ultimas24h = AvisoEnvio::where('ae_canal', 'whatsapp')
            ->where('created_at', '>=', now()->subDay())
            ->get();

        return [
            'estado'    => $estado,
            'etiqueta'  => $this->etiqueta($estado['estado']),
            'color'     => $this->color($estado['estado']),
            'enviados'  => $ultimas24h->where('ae_resultado', AvisoEnvio::ENVIADO)->count(),
            'fallidos'  => $ultimas24h->where('ae_resultado', AvisoEnvio::FALLIDO)->count(),
            'omitidos'  => $ultimas24h->where('ae_resultado', AvisoEnvio::OMITIDO)->count(),
        ];
    }

    private function etiqueta(string $estado): string
    {
        return match ($estado) {
            'abierta'        => 'Conectado',
            'conectando'     => 'Esperando el código QR',
            'cerrada'        => 'Desconectado: hay que volver a escanear el QR',
            'sin_configurar' => 'Sin configurar',
            'sin_respuesta'  => 'El gateway no responde',
            default          => $estado,
        };
    }

    private function color(string $estado): string
    {
        return match ($estado) {
            'abierta'    => 'success',
            'conectando' => 'warning',
            default      => 'danger',
        };
    }
}
