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
 * La tarjeta se dibuja siempre --hace falta para poder dejar el sonido activado
 * ANTES de que haya una emergencia, y para comprobar que se oye-- pero la lista
 * roja de alertas solo aparece cuando hay alguna: un bloque de emergencia
 * permanente se vuelve parte del decorado y deja de mirarse a los dos dias.
 */
class AlertasEnVivo extends Widget
{
    use AcotaPorAlcance;

    /**
     * El codigo de alerta mas alto que este navegador ya vio.
     *
     * ⚠️ La deteccion de «alerta nueva» se hace **en el servidor**, no en
     * JavaScript. El primer intento comparaba en el navegador los nodos del DOM
     * entre refrescos, y eso no funcionaba: el widget se dibuja solo cuando hay
     * alertas, asi que **la primera vez que aparece es dentro de un ciclo de
     * Livewire** -- y ahi `@push('scripts')` ya no llega al layout, con lo que la
     * funcion que debia sonar no existia. Justo en el unico momento que
     * importaba.
     *
     * Con la cuenta en una propiedad del componente, el servidor sabe cuando hay
     * algo nuevo y **despacha un evento**; el navegador solo tiene que oirlo.
     */
    public ?int $ultimoVisto = null;

    protected string $view = 'filament.widgets.alertas-en-vivo';

    protected static ?int $sort = -10;

    /*
     * Media columna, no el ancho completo.
     *
     * A ancho completo empujaba el resto del tablero hacia abajo y la tarjeta
     * quedaba con una franja vacia enorme a la derecha. Media columna deja al
     * lado la tarjeta de usuario, que es como estaba antes y como se veia bien.
     */
    protected int | string | array $columnSpan = [
        'default' => 'full',   // en movil no hay dos columnas
        'md' => 1,
    ];

    /*
     * El refresco lo hace la vista con `wire:poll.15s="comprobar"`, que ademas
     * de redibujar **compara y avisa si hay algo nuevo**. El `$pollingInterval`
     * de Filament solo redibuja, asi que tenerlo tambien significaba consultar
     * el doble para que la mitad de las veces no detectara nada.
     *
     * 15 s es un compromiso: medio minuto es mucho en una emergencia, y bajar de
     * 10 multiplica las consultas de todos los que tengan el panel abierto sin
     * ganar nada apreciable.
     */

    public static function canView(): bool
    {
        return PerfilPanel::puedeOperar();
    }

    /**
     * Las alertas sin resolver del alcance de quien mira.
     *
     * @return array<int, array<string, mixed>>
     */
    /**
     * Lo que corre en cada refresco: busca, y si hay algo nuevo, avisa.
     */
    public function comprobar(): void
    {
        $alertas = $this->getAlertas();

        if ($alertas === []) {
            return;
        }

        $mayor = max(array_column($alertas, 'code'));

        // En la primera carga NO suena: entrar al panel con una alerta abierta
        // de hace horas dispararia la alarma cada vez que alguien inicia sesion.
        if ($this->ultimoVisto === null) {
            $this->ultimoVisto = $mayor;

            return;
        }

        if ($mayor > $this->ultimoVisto) {
            $this->ultimoVisto = $mayor;
            $this->dispatch('emergencia-nueva');
        }
    }

    /**
     * Dispara el aviso como si acabara de entrar una emergencia.
     *
     * Existe para poder comprobar el circuito ENTERO --servidor despacha,
     * navegador recibe, suena-- sin tener que mandar una alerta de verdad desde
     * una tablet. El boton «Probar sonido» solo prueba el audio del navegador;
     * este prueba lo que realmente fallaba.
     *
     * No toca ningun dato: solo emite el evento.
     */
    public function probarAviso(): void
    {
        $this->dispatch('emergencia-nueva');
    }

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
