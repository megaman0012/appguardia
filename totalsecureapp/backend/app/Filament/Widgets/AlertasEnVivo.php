<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\AcotaPorAlcance;
use App\Support\PerfilPanel;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

/**
 * Las emergencias abiertas, a la vista y con sonido.
 *
 * ⚠️ **Existe porque avisar no alcanzaba.** Un botón de pánico generaba su
 * notificación correctamente --queda registrada y le llega a supervisores,
 * Consola y Administradores-- pero el único aviso visible era la campanita de
 * Filament: un número pequeño en un icono del encabezado. **No suena, no
 * interrumpe, y si nadie la mira, nadie se entera.** Para un aviso de turno está
 * bien; para una emergencia, no: el caso normal es que el panel esté abierto en
 * una pantalla que nadie está mirando en ese momento.
 *
 * Este widget se refresca solo cada 15 segundos y, cuando aparece una alerta que
 * no estaba, **suena y la pone en pantalla**. El sonido se genera con la Web
 * Audio API en vez de servir un archivo: no hay que desplegar ningún MP3 ni
 * depende de que la ruta exista.
 *
 * Se dibuja **sólo cuando hay algo**: un widget permanente en rojo se vuelve
 * parte del decorado y deja de mirarse a los dos días.
 */
class AlertasEnVivo extends Widget
{
    use AcotaPorAlcance;

    protected string $view = 'filament.widgets.alertas-en-vivo';

    protected static ?int $sort = -10;

    protected int | string | array $columnSpan = 'full';

    /**
     * Cada 15 s. Es un compromiso: medio minuto es mucho en una emergencia, y
     * bajar de 10 multiplica las consultas de todos los que tengan el panel
     * abierto sin ganar nada apreciable.
     */
    protected static ?string $pollingInterval = '15s';

    public static function canView(): bool
    {
        return PerfilPanel::puedeOperar();
    }

    /**
     * Las alertas sin resolver del alcance de quien mira.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAlertas(): array
    {
        $locales = $this->localesEnAlcance();

        if ($locales !== null && $locales === []) {
            return [];
        }

        $consulta = DB::table('alertas as a')
            ->leftJoin('organizacion_institucion as oi', 'oi.ins_code', '=', 'a.al_ins_code')
            ->leftJoin('users as u', 'u.id', '=', 'a.al_usu_id')
            ->whereIn('a.al_estado_alerta', ['pendiente', 'en_atencion'])
            ->where('a.al_estado', 1)
            /*
             * Sólo lo de las últimas 24 h. Sin este corte, una alerta vieja que
             * nadie cerró seguiría sonando para siempre y el aviso se volvería
             * ruido que todos aprenden a ignorar.
             */
            ->where('a.al_fecha', '>=', Carbon::now()->subDay())
            ->orderByDesc('a.al_code')
            ->limit(10);

        if ($locales !== null) {
            $consulta->whereIn('a.al_ins_code', $locales);
        }

        return $consulta->get([
            'a.al_code', 'a.al_fecha', 'a.al_prioridad', 'a.al_observacion',
            'a.al_lat', 'a.al_lng', 'a.al_estado_alerta',
            'oi.ins_descripcion as local', 'u.usu_nmbcom as guardia',
        ])->map(function ($a) {
            $lat = (float) $a->al_lat;
            $lng = (float) $a->al_lng;

            return [
                'code'       => (int) $a->al_code,
                'local'      => $a->local ?? 'Local desconocido',
                'guardia'    => $a->guardia ?? 'Desconocido',
                'motivo'     => $a->al_observacion,
                'prioridad'  => $a->al_prioridad,
                'estado'     => $a->al_estado_alerta,
                'hace'       => Carbon::parse($a->al_fecha)->diffForHumans(),
                // La app manda 0/0 cuando no hubo lectura de GPS: mandar a
                // alguien a esas coordenadas sería peor que no dar ninguna.
                'mapa'       => (abs($lat) > 0.0001 || abs($lng) > 0.0001)
                    ? sprintf('https://maps.google.com/?q=%s,%s', $a->al_lat, $a->al_lng)
                    : null,
            ];
        })->all();
    }
}
