<?php

namespace App\Services;

use App\Services\Avisos\CanalDeAviso;
use App\Services\Avisos\ResultadoDeAviso;
use Modules\Administracion\Models\AvisoEnvio;
use Illuminate\Support\Facades\DB;
use Modules\Administracion\Models\TurnoPostulacion;
use Modules\Administracion\Models\TurnoVacante;

/**
 * A quién se le avisa cuando un puesto queda vacío.
 *
 * Un aviso nunca puede hacer fallar lo que lo originó: si el envío revienta, la
 * vacante ya quedó abierta y el guardia igual la ve en la pantalla "Turnos
 * disponibles". El aviso acelera, no habilita.
 *
 * Los canales salen de `config/avisos.php`; ver App\Services\Avisos\CanalDeAviso
 * para agregar WhatsApp o SMS.
 */
class NotificadorVacante
{
    /** @var CanalDeAviso[] */
    private array $canales;

    public function __construct(private VacanteService $vacantes)
    {
        $this->canales = array_map(
            fn (string $clase) => app($clase),
            config('avisos.canales', [])
        );
    }

    /**
     * La falta se detectó: se le avisa a quien tiene que decidir.
     *
     * Cubrir un puesto es responsabilidad del Líder Operativo, así que el aviso
     * va primero a él; el supervisor del local lo recibe también porque es quien
     * puede confirmar en el momento si el guardia llegó o no.
     */
    public function faltaDetectada(TurnoVacante $vacante): int
    {
        return $this->avisarA(
            $this->responsables($vacante),
            'Puesto sin cubrir',
            sprintf(
                '%s no registró entrada en %s. Confirme si hay que cubrir el turno.',
                optional($vacante->ausente)->usu_nmbcom ?? 'El guardia asignado',
                $vacante->descripcion
            ),
            $vacante,
            'falta_detectada'
        );
    }

    /**
     * El turno se ofrece: se le avisa a los guardias que pueden tomarlo.
     *
     * El mensaje pide una respuesta porque el guardia que puede cubrir está
     * **franco, en su casa**: la app vive en las tablets de los puestos, no en su
     * teléfono. WhatsApp es el único camino hasta él, así que el aviso tiene que
     * poder contestarse desde WhatsApp.
     */
    public function vacanteAbierta(TurnoVacante $vacante): int
    {
        return $this->avisarA(
            $this->vacantes->elegibles($vacante)->pluck('id')->all(),
            'Turno por cubrir',
            $this->convocatoria($vacante),
            $vacante,
            'vacante_abierta'
        );
    }

    /**
     * Texto de la convocatoria.
     *
     * Lleva el número de la vacante porque un guardia puede tener dos ofertas
     * abiertas a la vez; sin el código no habría forma de saber cuál aceptó.
     */
    public function convocatoria(TurnoVacante $vacante): string
    {
        return sprintf(
            "Hay un turno por cubrir:\n%s\n%s\n\nPara tomarlo responda: SI %d\nSi no puede, responda: NO %d",
            optional($vacante->institucion)->ins_descripcion ?? 'Local',
            $vacante->descripcion,
            $vacante->tv_id,
            $vacante->tv_id
        );
    }

    /**
     * Se abrió a toda la ciudad: se avisa solo a los que ANTES no podían verlo.
     *
     * Volver a avisarle a los del local, que ya recibieron el primer aviso y no
     * se postularon, sería insistir sin información nueva.
     */
    public function vacanteEscalada(TurnoVacante $vacante, array $yaAvisados): int
    {
        $nuevos = array_diff(
            $this->vacantes->elegibles($vacante)->pluck('id')->all(),
            $yaAvisados
        );

        return $this->avisarA(
            $nuevos,
            'Turno por cubrir en su ciudad',
            "Es en otro local de su ciudad.\n\n" . $this->convocatoria($vacante),
            $vacante,
            'vacante_escalada'
        );
    }

    /**
     * Un guardia contestó que puede cubrir.
     *
     * Le llega a la Consola y al Líder, que son quienes deciden. Sin esto, la
     * respuesta del guardia quedaría esperando a que alguien entre al panel a
     * mirar, que es justo lo que no pasa a las tres de la mañana.
     */
    public function postulacionRecibida(TurnoVacante $vacante, int $usuarioId): int
    {
        $guardia = DB::table('users')->where('id', $usuarioId)->value('usu_nmbcom') ?? "Usuario {$usuarioId}";

        return $this->avisarA(
            $this->responsables($vacante),
            'Un guardia aceptó cubrir',
            sprintf('%s puede cubrir %s. Confírmelo en el panel.', $guardia, $vacante->descripcion),
            $vacante,
            'postulacion_recibida'
        );
    }

    /** Se eligió quién cubre: al elegido y a los que no quedaron. */
    public function coberturaConfirmada(TurnoVacante $vacante, TurnoPostulacion $aceptada): int
    {
        $enviados = $this->avisarA(
            [(int) $aceptada->tp_usu_id],
            'Turno confirmado',
            sprintf('Usted cubre el turno: %s. Preséntese a la hora de entrada.', $vacante->descripcion),
            $vacante,
            'cobertura_confirmada'
        );

        $rechazados = $vacante->postulaciones()
            ->where('tp_estado', TurnoPostulacion::RECHAZADA)
            ->where('tp_id', '!=', $aceptada->tp_id)
            ->pluck('tp_usu_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // Se les avisa igual: quedarse esperando una respuesta que no llega es
        // peor que un "ya está cubierto".
        return $enviados + $this->avisarA(
            $rechazados,
            'Turno ya cubierto',
            sprintf('El turno %s fue asignado a otro guardia. Gracias por ofrecerse.', $vacante->descripcion),
            $vacante,
            'cobertura_asignada_a_otro'
        );
    }

    /**
     * Quién puede resolver esta vacante.
     *
     * @return int[]
     */
    public function responsables(TurnoVacante $vacante): array
    {
        // El país del local: el alcance del Líder Operativo se define así.
        $pais = DB::table('organizacion_institucion as oi')
            ->join('ciudad as c', 'c.cd_id', '=', 'oi.ins_cd_id')
            ->join('provincia as p', 'p.pr_id', '=', 'c.cd_pr_id')
            ->where('oi.ins_code', $vacante->tv_ins_code)
            ->value('p.pr_pa_id');

        // Un local sin ciudad no pertenece a ningún país, así que ningún líder
        // lo tiene en su alcance: queda en manos del supervisor del local.
        $lideres = $pais
            ? DB::table('users as u')
                ->join('user_has_roles as ur', 'ur.user_id', '=', 'u.id')
                ->join('roles as r', 'r.id', '=', 'ur.role_id')
                ->join('user_has_pais as up', 'up.up_usu_id', '=', 'u.id')
                ->where('up.up_pa_id', $pais)
                ->where('up.up_estado', true)
                ->where('u.usu_state', 1)
                ->where('r.name', 'Lider Operativo')
                ->pluck('u.id')
            : collect();

        $supervisores = DB::table('users as u')
            ->join('user_has_roles as ur', 'ur.user_id', '=', 'u.id')
            ->join('roles as r', 'r.id', '=', 'ur.role_id')
            ->join('user_has_institucion as ui', 'ui.ui_usu_id', '=', 'u.id')
            ->where('ui.ui_ins_code', $vacante->tv_ins_code)
            ->where('ui.ui_state', 1)
            ->where('u.usu_state', 1)
            ->where('r.name', 'Supervisor')
            ->pluck('u.id');

        // La Consola atiende toda la operación: no se acota por local ni por
        // país, porque de madrugada es la única que va a estar mirando.
        $consola = DB::table('users as u')
            ->join('user_has_roles as ur', 'ur.user_id', '=', 'u.id')
            ->join('roles as r', 'r.id', '=', 'ur.role_id')
            ->where('u.usu_state', 1)
            ->where('r.name', 'Consola')
            ->pluck('u.id');

        return $consola->merge($lideres)->merge($supervisores)
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Deja constancia del intento, haya salido o no.
     *
     * Lo que no salió es justamente lo que hay que poder ver: cuando un puesto
     * amanece vacío, la pregunta es si el guardia se enteró.
     */
    private function registrar(
        int $usuarioId,
        CanalDeAviso $canal,
        ResultadoDeAviso $resultado,
        string $titulo,
        string $cuerpo,
        TurnoVacante $vacante,
        string $tipo
    ): void {
        try {
            AvisoEnvio::create([
                'ae_usu_id'    => $usuarioId,
                'ae_canal'     => $canal->nombre(),
                'ae_tipo'      => $tipo,
                'ae_titulo'    => $titulo,
                'ae_cuerpo'    => $cuerpo,
                'ae_destino'   => $resultado->destino,
                'ae_resultado' => $resultado->resultado,
                'ae_detalle'   => $resultado->detalle,
                'ae_tv_id'     => $vacante->tv_id,
            ]);
        } catch (\Throwable $e) {
            // Ni siquiera el registro puede tumbar la operación.
            report($e);
        }
    }

    /**
     * @param int[] $usuarios
     * @return int cuántos avisos salieron por al menos un canal
     */
    private function avisarA(array $usuarios, string $titulo, string $cuerpo, TurnoVacante $vacante, string $tipo): int
    {
        $datos = [
            'tipo'     => $tipo,
            'tv_id'    => $vacante->tv_id,
            'ins_code' => $vacante->tv_ins_code,
            'pantalla' => 'Vacantes',
        ];

        $enviados = 0;

        foreach (array_unique($usuarios) as $usuarioId) {
            $llego = false;

            foreach ($this->canales as $canal) {
                try {
                    $resultado = $canal->enviar((int) $usuarioId, $titulo, $cuerpo, $datos);
                } catch (\Throwable $e) {
                    // Un canal caído no puede tumbar al resto ni a la operación.
                    report($e);
                    $resultado = ResultadoDeAviso::fallido($e->getMessage());
                }

                $this->registrar((int) $usuarioId, $canal, $resultado, $titulo, $cuerpo, $vacante, $tipo);

                $llego = $resultado->ok() || $llego;
            }

            $enviados += $llego ? 1 : 0;
        }

        return $enviados;
    }
}
