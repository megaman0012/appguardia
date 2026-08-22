<?php

namespace App\Observers;

use App\Services\DashboardStatsService;
use Modules\Administracion\Models\Alertas;

/**
 * Invalida el cache del dashboard cuando cambia una alerta (Fase 9).
 *
 * Va en un observer y no en AlertaService para que tambien cubra las escrituras
 * que no pasan por el servicio: el panel Filament, los seeders y cualquier
 * camino futuro. Un contador de alertas activas desactualizado es peor que uno
 * lento.
 */
class AlertaObserver
{
    private DashboardStatsService $stats;

    public function __construct(DashboardStatsService $stats)
    {
        $this->stats = $stats;
    }

    public function created(Alertas $alerta): void
    {
        $this->invalidar($alerta);
    }

    public function updated(Alertas $alerta): void
    {
        $this->invalidar($alerta);
    }

    public function deleted(Alertas $alerta): void
    {
        $this->invalidar($alerta);
    }

    private function invalidar(Alertas $alerta): void
    {
        // Si la alerta cambio de institucion hay que invalidar las dos.
        $codigos = array_unique(array_filter([
            $alerta->al_ins_code,
            $alerta->getOriginal('al_ins_code'),
        ]));

        foreach ($codigos as $insCode) {
            $this->stats->invalidar(DashboardStatsService::DOMINIO_ALERTAS, (int) $insCode);
        }
    }
}
