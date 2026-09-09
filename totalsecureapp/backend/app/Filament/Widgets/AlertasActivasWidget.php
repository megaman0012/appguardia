<?php

namespace App\Filament\Widgets;

use App\Services\DashboardStatsService;
use App\Filament\Widgets\Concerns\AcotaPorAlcance;
use Filament\Widgets\StatsOverviewWidget;
use Illuminate\Support\Facades\DB;
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
    use AcotaPorAlcance;

    protected static ?int $sort = 4;

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
        return 'Alertas';
    }

    protected function getDescription(): ?string
    {
        return null;
    }

    protected function getStats(): array
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
                ->icon('heroicon-o-exclamation-triangle'),

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
