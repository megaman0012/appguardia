<?php

namespace App\Livewire;

use App\Support\PerfilPanel;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Livewire\Component;

/**
 * La alarma de emergencia, en TODAS las paginas del panel.
 *
 * ⚠️ **Por que existe, habiendo ya un widget.** El widget `AlertasEnVivo` solo
 * se monta en el tablero. Quien esta revisando Usuarios, Turnos o Accesos --que
 * es donde se pasa el tiempo-- no lo tiene en pantalla, asi que **no hay nada
 * que consulte ni que suene**. Los botones de prueba funcionaban porque se
 * pulsaban estando en el tablero; una emergencia real entrando mientras se
 * trabaja en otra pantalla no sonaba nunca.
 *
 * Este componente se inyecta por `PanelsRenderHook::BODY_END`, asi que vive en
 * todas las paginas del panel y sigue vigilando se este donde se este.
 *
 * No dibuja nada visible salvo cuando hay algo: no compite con el widget del
 * tablero, que es el que muestra el detalle.
 */
class AvisoDeEmergencia extends Component
{
    /** El codigo de alerta mas alto que esta sesion ya vio. */
    public ?int $ultimoVisto = null;

    /** Cuantas hay abiertas ahora, para el aviso flotante. */
    public int $abiertas = 0;

    public function comprobar(): void
    {
        $mayor = $this->mayorAbierta();

        $this->abiertas = $this->contarAbiertas();

        if ($mayor === null) {
            return;
        }

        /*
         * En la primera carga NO suena: entrar al panel con una emergencia
         * abierta de hace horas dispararia la alarma cada vez que alguien inicia
         * sesion, y a los dos dias nadie le haria caso.
         */
        if ($this->ultimoVisto === null) {
            $this->ultimoVisto = $mayor;

            return;
        }

        if ($mayor > $this->ultimoVisto) {
            $this->ultimoVisto = $mayor;
            $this->dispatch('emergencia-nueva');
        }
    }

    /** Para probar el circuito entero sin mandar una alerta de verdad. */
    public function probarAviso(): void
    {
        $this->dispatch('emergencia-nueva');
    }

    private function consulta()
    {
        $q = DB::table('alertas')
            ->whereIn('al_estado_alerta', ['pendiente', 'en_atencion'])
            ->where('al_estado', 1)
            // Solo lo del ultimo dia: una alerta vieja que nadie cerro no puede
            // seguir sonando para siempre.
            ->where('al_fecha', '>=', Carbon::now()->subDay());

        $locales = $this->localesEnAlcance();

        if ($locales === null) {
            return $q;
        }

        return empty($locales) ? $q->whereRaw('1 = 0') : $q->whereIn('al_ins_code', $locales);
    }

    private function mayorAbierta(): ?int
    {
        $v = $this->consulta()->max('al_code');

        return $v === null ? null : (int) $v;
    }

    private function contarAbiertas(): int
    {
        return (int) $this->consulta()->count();
    }

    /**
     * @return int[]|null null = ve todo; [] = no ve nada
     */
    private function localesEnAlcance(): ?array
    {
        if (PerfilPanel::alcanceEsGlobal()) {
            return null;
        }

        if (PerfilPanel::alcanceEsPorInstitucion()) {
            $usuId = Session::get('usuID');

            if (!$usuId) {
                return [];
            }

            return DB::table('user_has_institucion')
                ->where('ui_usu_id', $usuId)
                ->where('ui_state', 1)
                ->pluck('ui_ins_code')
                ->map(fn ($v) => (int) $v)
                ->all();
        }

        return PerfilPanel::localesDelUsuario();
    }

    public function render()
    {
        return view('livewire.aviso-de-emergencia');
    }
}
