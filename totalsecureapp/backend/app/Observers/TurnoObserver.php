<?php

namespace App\Observers;

use App\Services\DashboardStatsService;
use Modules\Administracion\Models\Turno;

/**
 * Invalida el cache de cumplimiento cuando cambia un turno (Fase 9).
 *
 * Cubre tanto el marcaje del guardia (que actualiza el turno via TurnoService)
 * como el cierre automatico del command turnos:cerrar-dia.
 */
class TurnoObserver
{
    private DashboardStatsService $stats;

    public function __construct(DashboardStatsService $stats)
    {
        $this->stats = $stats;
    }

    public function created(Turno $turno): void
    {
        $this->invalidar($turno);
    }

    public function updated(Turno $turno): void
    {
        $this->invalidar($turno);
    }

    public function deleted(Turno $turno): void
    {
        $this->invalidar($turno);
    }

    private function invalidar(Turno $turno): void
    {
        $codigos = array_unique(array_filter([
            $turno->tu_ins_code,
            $turno->getOriginal('tu_ins_code'),
        ]));

        foreach ($codigos as $insCode) {
            $this->stats->invalidar(DashboardStatsService::DOMINIO_TURNOS, (int) $insCode);
        }
    }
}
