<?php

namespace App\Services;

use App\Services\Avisos\CanalDeAviso;
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

    /** El turno se ofrece: se le avisa a los guardias que pueden tomarlo. */
    public function vacanteAbierta(TurnoVacante $vacante): int
    {
        return $this->avisarA(
            $this->vacantes->elegibles($vacante)->pluck('id')->all(),
            'Turno disponible',
            sprintf('Hay un turno por cubrir: %s. Puede postularse desde la app.', $vacante->descripcion),
            $vacante,
            'vacante_abierta'
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
            'Turno disponible en su ciudad',
            sprintf('Hay un turno por cubrir en otro local: %s.', $vacante->descripcion),
            $vacante,
            'vacante_escalada'
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

        return $lideres->merge($supervisores)
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
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
                    $llego = $canal->enviar((int) $usuarioId, $titulo, $cuerpo, $datos) || $llego;
                } catch (\Throwable $e) {
                    // Un canal caído no puede tumbar al resto ni a la operación.
                    report($e);
                }
            }

            $enviados += $llego ? 1 : 0;
        }

        return $enviados;
    }
}
