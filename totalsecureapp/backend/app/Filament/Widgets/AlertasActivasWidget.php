<?php

namespace App\Filament\Widgets;

use App\Services\DashboardStatsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Card;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Session;
use Modules\Administracion\Models\UserHasInstitucion;

/**
 * Alertas activas de las instituciones del usuario (Fase 9).
 *
 * Los conteos salen de DashboardStatsService, cacheados con invalidacion por
 * evento: al atender una alerta el observer sube la version y el widget muestra
 * el numero nuevo en la recarga siguiente, sin esperar un TTL.
 */
class AlertasActivasWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getCards(): array
    {
        $totales = ['activas' => 0, 'pendientes' => 0, 'en_atencion' => 0, 'criticas' => 0, 'altas' => 0];
        $masAntigua = null;

        $stats = app(DashboardStatsService::class);

        foreach ($this->institucionesDelUsuario() as $insCode) {
            $datos = $stats->alertasActivas($insCode);

            foreach (array_keys($totales) as $clave) {
                $totales[$clave] += $datos[$clave];
            }

            if ($datos['mas_antigua'] !== null
                && ($masAntigua === null || $datos['mas_antigua'] < $masAntigua)) {
                $masAntigua = $datos['mas_antigua'];
            }
        }

        $urgentes = $totales['criticas'] + $totales['altas'];

        return [
            Card::make('Alertas activas', $totales['activas'])
                ->description($totales['pendientes'] . ' sin atender, ' . $totales['en_atencion'] . ' en atención')
                ->color($totales['pendientes'] > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-exclamation'),

            Card::make('Críticas y altas', $urgentes)
                ->description($urgentes > 0 ? 'Requieren atención inmediata' : 'Ninguna pendiente')
                ->color($urgentes > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-fire'),

            Card::make('Más antigua sin cerrar', $this->antiguedad($masAntigua))
                ->description($masAntigua ? 'Desde ' . Carbon::parse($masAntigua)->format('d/m H:i') : 'Sin alertas abiertas')
                ->color($this->colorAntiguedad($masAntigua))
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

    private function antiguedad(?string $fecha): string
    {
        if ($fecha === null) {
            return '—';
        }

        $minutos = Carbon::parse($fecha)->diffInMinutes(now());

        if ($minutos < 60) {
            return $minutos . ' min';
        }
        if ($minutos < 1440) {
            return intdiv($minutos, 60) . ' h';
        }

        return intdiv($minutos, 1440) . ' d';
    }

    private function colorAntiguedad(?string $fecha): string
    {
        if ($fecha === null) {
            return 'success';
        }

        $minutos = Carbon::parse($fecha)->diffInMinutes(now());

        return $minutos > 120 ? 'danger' : ($minutos > 30 ? 'warning' : 'success');
    }
}
