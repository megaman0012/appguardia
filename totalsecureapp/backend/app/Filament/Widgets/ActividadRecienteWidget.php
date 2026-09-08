<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\AcotaPorAlcance;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Card;
use Modules\Administracion\Models\Acceso;
use Modules\Administracion\Models\Novedad;
use Modules\Administracion\Models\ronda_cabecera;
use Modules\Administracion\Models\user_has_biometria;

/**
 * Que se registro en campo en los ultimos 7 dias.
 *
 * **Va en 7 dias y no en «hoy» a proposito.** Un escritorio que mide el dia
 * corriente amanece en cero todas las mañanas y arranca el turno de la noche sin
 * nada que mostrar; peor, mientras el sistema no este en operacion real se ve
 * vacio y parece roto. Una ventana de 7 dias siempre tiene contenido y sigue
 * sirviendo cuando la operacion arranque.
 *
 * Cada tarjeta compara contra los 7 dias anteriores, porque el numero solo no
 * dice nada: 452 marcajes esta bien o mal segun si la semana pasada fueron 400 o
 * 900.
 */
class ActividadRecienteWidget extends StatsOverviewWidget
{
    use AcotaPorAlcance;

    protected static ?int $sort = 1;

    /**
     * Vista propia solo para poder poner un titulo.
     *
     * Filament 2 no soporta encabezado en StatsOverviewWidget, y con dos filas
     * de cuatro tarjetas quedaban ocho numeros seguidos sin distinguir cual mide
     * operacion y cual mide configuracion.
     */
    protected static string $view = 'filament.widgets.stats-con-titulo';

    public function getEncabezado(): ?string
    {
        return 'Actividad de los últimos 7 días';
    }

    public function getAyuda(): ?string
    {
        return 'Lo que los guardias registraron en campo, comparado con la semana anterior.';
    }

    protected function getCards(): array
    {
        return [
            $this->tarjeta(
                'Marcajes',
                user_has_biometria::class,
                'bio_ins_code',
                'bio_created_at',
                'heroicon-o-finger-print'
            ),
            $this->tarjeta(
                'Rondas',
                ronda_cabecera::class,
                'rc_ins_code',
                'rc_fecha_inicio',
                'heroicon-o-map'
            ),
            $this->tarjeta(
                'Accesos',
                Acceso::class,
                'ac_ins_code',
                'ac_created_at',
                'heroicon-o-login'
            ),
            $this->tarjeta(
                'Novedades',
                Novedad::class,
                'nv_ins_code',
                'nv_fecha_hora',
                'heroicon-o-annotation'
            ),
        ];
    }

    /**
     * Una tarjeta con el conteo de la semana y su variacion.
     *
     * @param class-string $modelo
     */
    private function tarjeta(
        string $titulo,
        string $modelo,
        string $columnaLocal,
        string $columnaFecha,
        string $icono
    ): Card {
        $semana = $this->contar($modelo, $columnaLocal, $columnaFecha, 7, 0);
        $anterior = $this->contar($modelo, $columnaLocal, $columnaFecha, 14, 7);

        return Card::make($titulo, number_format($semana, 0, ',', '.'))
            ->description($this->variacion($semana, $anterior))
            ->descriptionIcon($this->iconoVariacion($semana, $anterior))
            ->color($this->colorVariacion($semana, $anterior))
            ->icon($icono);
    }

    /** @param class-string $modelo */
    private function contar(
        string $modelo,
        string $columnaLocal,
        string $columnaFecha,
        int $desdeDias,
        int $hastaDias
    ): int {
        $q = $modelo::query()
            ->where($columnaFecha, '>=', now()->subDays($desdeDias));

        if ($hastaDias > 0) {
            $q->where($columnaFecha, '<', now()->subDays($hastaDias));
        }

        return (int) $this->acotar($q, $columnaLocal)->count();
    }

    private function variacion(int $ahora, int $antes): string
    {
        if ($antes === 0) {
            return $ahora === 0 ? 'Sin registros' : 'Sin comparacion previa';
        }

        $pct = (int) round((($ahora - $antes) / $antes) * 100);

        if ($pct === 0) {
            return 'Igual que la semana anterior';
        }

        return sprintf(
            '%s%d%% vs. semana anterior (%s)',
            $pct > 0 ? '+' : '',
            $pct,
            number_format($antes, 0, ',', '.')
        );
    }

    private function iconoVariacion(int $ahora, int $antes): ?string
    {
        if ($antes === 0 || $ahora === $antes) {
            return null;
        }

        return $ahora > $antes ? 'heroicon-s-trending-up' : 'heroicon-s-trending-down';
    }

    /**
     * Una caida se pinta en ambar, no en rojo.
     *
     * Menos marcajes que la semana pasada puede ser un puesto que dejo de
     * operar, pero tambien un feriado o un contrato que termino. Es algo para
     * mirar, no una falla.
     */
    private function colorVariacion(int $ahora, int $antes): string
    {
        if ($antes === 0) {
            return 'secondary';
        }

        if ($ahora < $antes * 0.7) {
            return 'warning';
        }

        return 'success';
    }
}
