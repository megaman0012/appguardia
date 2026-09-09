<?php

namespace App\Filament\Widgets;

use App\Services\DashboardStatsService;
use App\Filament\Widgets\Concerns\AcotaPorAlcance;
use Filament\Widgets\StatsOverviewWidget;
use Illuminate\Support\Facades\DB;
use Filament\Widgets\StatsOverviewWidget\Stat;
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
    use AcotaPorAlcance;

    protected static ?int $sort = 5;

    /*
     * ⚠️ Antes esto declaraba `$view = 'filament.widgets.stats-con-titulo'`, una
     * vista propia que existia **solo para poder poner un titulo**: Filament 2
     * no soportaba encabezado en `StatsOverviewWidget` y con dos filas de cuatro
     * tarjetas quedaban ocho numeros seguidos sin distinguir cual mide operacion
     * y cual mide configuracion.
     *
     * **Filament 3 lo trae de fabrica** con `getHeading()` y `getDescription()`,
     * asi que la vista se borro y los metodos `getEncabezado()`/`getAyuda()`
     * pasaron a los nombres de la libreria. Era uno de los arreglos que el
     * roadmap anotaba como ganancia de esta subida.
     */

    protected function getHeading(): ?string
    {
        return 'Turnos de hoy';
    }

    protected function getDescription(): ?string
    {
        return 'Requiere cuadrantes cargados: sin turnos programados queda en cero.';
    }

    protected function getStats(): array
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
            Stat::make('Turnos de hoy', $total)
                ->description($total > 0 ? $conEntrada . ' con entrada marcada' : 'Sin turnos programados')
                ->color($total > 0 ? 'primary' : 'secondary')
                ->icon('heroicon-o-calendar'),

            Stat::make('Cumplimiento', $porcentaje === null ? '—' : $porcentaje . '%')
                ->description($sinMarcar > 0 ? $sinMarcar . ' sin marcar' : 'Todos marcaron')
                ->color($this->colorCumplimiento($porcentaje))
                ->icon('heroicon-o-check-circle'),

            Stat::make('Con tardanza', $conTardanza)
                ->description($minutosTardanza > 0 ? $minutosTardanza . ' min acumulados' : 'Sin tardanzas')
                ->color($conTardanza > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-clock'),
        ];
    }

    /**
     * @return int[]
     *
     * Antes leia solo `user_has_institucion`, y con eso un Administrador o la
     * Consola -- alcance global, sin locales vinculados -- veian el widget en
     * CERO. Ahora sale de `localesEnAlcance()`; con alcance global se resuelven
     * todos los locales, que es lo que corresponde ver.
     */
    private function institucionesDelUsuario(): array
    {
        $locales = $this->localesEnAlcance();

        if ($locales !== null) {
            return $locales;
        }

        return DB::table('organizacion_institucion')
            ->pluck('ins_code')
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
