<?php

namespace App\Filament\Widgets;

use App\Services\DashboardStatsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Card;
use Illuminate\Support\Facades\Session;
use Modules\Administracion\Models\UserHasInstitucion;

/**
 * Cumplimiento de turnos del dia (Fase 9).
 *
 * Cacheado por institucion y fecha, invalidado por el observer de Turno: el
 * marcaje de un guardia mueve el porcentaje sin esperar un TTL.
 */
class CumplimientoTurnosWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected function getCards(): array
    {
        $total = $conEntrada = $sinMarcar = $conTardanza = $minutosTardanza = 0;

        $stats = app(DashboardStatsService::class);

        foreach ($this->institucionesDelUsuario() as $insCode) {
            $datos = $stats->cumplimientoTurnos($insCode);

            $total           += $datos['total'];
            $conEntrada      += $datos['con_entrada'];
            $sinMarcar       += $datos['sin_marcar'];
            $conTardanza     += $datos['con_tardanza'];
            $minutosTardanza += $datos['minutos_tardanza'];
        }

        // Sin turnos programados no es 0% de cumplimiento, es que no aplica.
        $porcentaje = $total > 0 ? round($conEntrada * 100 / $total, 1) : null;

        return [
            Card::make('Turnos de hoy', $total)
                ->description($total > 0 ? $conEntrada . ' con entrada marcada' : 'Sin turnos programados')
                ->color($total > 0 ? 'primary' : 'secondary')
                ->icon('heroicon-o-calendar'),

            Card::make('Cumplimiento', $porcentaje === null ? '—' : $porcentaje . '%')
                ->description($sinMarcar > 0 ? $sinMarcar . ' sin marcar' : 'Todos marcaron')
                ->color($this->colorCumplimiento($porcentaje))
                ->icon('heroicon-o-check-circle'),

            Card::make('Con tardanza', $conTardanza)
                ->description($minutosTardanza > 0 ? $minutosTardanza . ' min acumulados' : 'Sin tardanzas')
                ->color($conTardanza > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-clock'),
        ];
    }

    /** @return int[] */
    private function institucionesDelUsuario(): array
    {
        $usuId = Session::get('usuID');
        if (!$usuId) {
            return [];
        }

        return UserHasInstitucion::where('ui_usu_id', $usuId)
            ->where('ui_state', 1)
            ->pluck('ui_ins_code')
            ->map(fn ($c) => (int) $c)
            ->all();
    }

    private function colorCumplimiento(?float $porcentaje): string
    {
        if ($porcentaje === null) {
            return 'secondary';
        }

        return $porcentaje >= 90 ? 'success' : ($porcentaje >= 70 ? 'warning' : 'danger');
    }
}
