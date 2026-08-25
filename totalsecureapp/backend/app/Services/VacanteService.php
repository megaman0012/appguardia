<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Administracion\Models\Turno;
use Modules\Administracion\Models\TurnoPostulacion;
use Modules\Administracion\Models\TurnoVacante;

/**
 * Cubrir un turno que quedó vacío.
 *
 * Tres reglas de fondo, y las tres tienen un porqué que no es técnico:
 *
 *  1. El sistema DETECTA la falta, pero no la declara. Los marcajes se pueden
 *     hacer sin señal y sincronizar horas después, así que "no marcó" no
 *     significa "no vino". Si abriera convocatorias solo, publicaríamos vacantes
 *     por un teléfono sin cobertura. Confirma una persona.
 *  2. El aviso sale en dos olas: primero los guardias del propio local, que ya
 *     tienen la acreditación del cliente; después, si nadie responde, el resto
 *     de la ciudad. Los locales marcados como que exigen credencial propia nunca
 *     salen de la primera ola: ofrecerles un turno al que no pueden entrar es
 *     peor que no ofrecerlo.
 *  3. El turno del que faltó NO se reasigna. Queda como ausencia y se crea un
 *     turno nuevo para quien cubre. Reasignarlo borraría la falta, que es
 *     justamente el dato que después hay que poder mirar.
 */
class VacanteService
{
    /** Minutos de gracia antes de sospechar que el guardia no llegó. */
    public const TOLERANCIA_MINUTOS = 20;

    /** Minutos sin postulaciones antes de abrir la vacante a toda la ciudad. */
    public const MINUTOS_PARA_ESCALAR = 30;

    /** Descanso mínimo entre turnos, el mismo que valida el cuadrante. */
    private const DESCANSO_MINIMO_MINUTOS = 8 * 60;

    public function __construct(private OfflineSyncService $sync)
    {
    }

    // ── Detección ──

    /**
     * Busca turnos que ya deberían haber empezado y nadie marcó.
     *
     * Crea la vacante en estado "detectada": no se le avisa a nadie todavía.
     * Es una pregunta al supervisor, no una convocatoria.
     *
     * @return int cuántas detectó
     */
    public function detectar(?Carbon $ahora = null): int
    {
        $ahora  = $ahora ?? Carbon::now();
        $limite = $ahora->copy()->subMinutes(self::TOLERANCIA_MINUTOS);

        // Ventana corta: turnos de ayer y hoy, para no arrastrar historia vieja
        // si el comando estuvo caído.
        $candidatos = Turno::with('vacantesVivas')
            ->where('tu_estado', 'programado')
            ->where('tu_state', true)
            ->whereNull('tu_marcada_entrada')
            ->whereBetween('tu_fecha', [
                $ahora->copy()->subDay()->toDateString(),
                $ahora->toDateString(),
            ])
            ->get();

        $detectadas = 0;

        foreach ($candidatos as $turno) {
            $inicio = $this->momento($turno->tu_fecha, $turno->tu_hora_inicio_prevista);
            $fin    = $this->fin($inicio, $turno->tu_hora_fin_prevista);

            // Todavía dentro de la tolerancia: puede estar llegando.
            if ($inicio->gt($limite)) {
                continue;
            }

            // El turno ya terminó: cubrirlo no tendría sentido.
            if ($fin->lt($ahora)) {
                continue;
            }

            if ($turno->vacantesVivas->isNotEmpty()) {
                continue;
            }

            if ($this->crearDesdeTurno($turno)) {
                $detectadas++;
            }
        }

        return $detectadas;
    }

    /** @return TurnoVacante|null null si ya existía una viva para ese turno */
    public function crearDesdeTurno(Turno $turno, string $motivo = 'falta'): ?TurnoVacante
    {
        try {
            return TurnoVacante::create([
                'tv_turno_id'       => $turno->tu_id,
                'tv_ins_code'       => $turno->tu_ins_code,
                'tv_puesto_id'      => $turno->tu_puesto_id,
                'tv_usu_id_ausente' => $turno->tu_usu_id,
                'tv_fecha'          => $turno->tu_fecha->toDateString(),
                'tv_hora_inicio'    => $turno->tu_hora_inicio_prevista,
                'tv_hora_fin'       => $turno->tu_hora_fin_prevista,
                'tv_motivo'         => $motivo,
                'tv_estado'         => TurnoVacante::DETECTADA,
            ]);
        } catch (QueryException $e) {
            // El índice único parcial evita que dos pasadas del detector, o el
            // detector y el supervisor a la vez, dupliquen la vacante.
            if ($this->sync->esViolacionUnica($e)) {
                return null;
            }

            throw $e;
        }
    }

    // ── Ciclo de vida ──

    /** El supervisor confirma que la falta es real y la ofrece. */
    public function abrir(TurnoVacante $vacante, ?int $usuarioId = null): TurnoVacante
    {
        $vacante->tv_estado     = TurnoVacante::ABIERTA;
        $vacante->tv_alcance    = TurnoVacante::ALCANCE_LOCAL;
        $vacante->tv_abierta_por = $usuarioId;
        $vacante->tv_abierta_en = Carbon::now();
        $vacante->save();

        return $vacante;
    }

    public function cancelar(TurnoVacante $vacante, ?int $usuarioId = null, ?string $motivo = null): TurnoVacante
    {
        $vacante->tv_estado = TurnoVacante::CANCELADA;
        $vacante->tv_observaciones = $motivo;
        $vacante->tv_confirmada_por = $usuarioId;
        $vacante->tv_confirmada_en = Carbon::now();
        $vacante->save();

        $vacante->postulaciones()->vigentes()->update(['tp_estado' => TurnoPostulacion::RECHAZADA]);

        return $vacante;
    }

    /**
     * Abre a toda la ciudad las vacantes que nadie del local tomó.
     *
     * @return int cuántas escaló
     */
    public function escalarAlcance(?Carbon $ahora = null): int
    {
        $ahora = $ahora ?? Carbon::now();

        $vacantes = TurnoVacante::abiertas()
            ->where('tv_alcance', TurnoVacante::ALCANCE_LOCAL)
            ->whereNotNull('tv_abierta_en')
            ->where('tv_abierta_en', '<=', $ahora->copy()->subMinutes(self::MINUTOS_PARA_ESCALAR))
            ->with(['institucion', 'postulaciones'])
            ->get();

        $escaladas = 0;

        foreach ($vacantes as $vacante) {
            if ($vacante->postulaciones->where('tp_estado', TurnoPostulacion::POSTULADO)->isNotEmpty()) {
                continue; // ya hay a quién elegir
            }

            // El local exige credencial propia: no se sale del local.
            if (optional($vacante->institucion)->ins_requiere_acreditacion) {
                continue;
            }

            $vacante->tv_alcance = TurnoVacante::ALCANCE_CIUDAD;
            $vacante->save();
            $escaladas++;
        }

        return $escaladas;
    }

    /** Cierra las que ya no se pueden cubrir porque el turno terminó. */
    public function vencer(?Carbon $ahora = null): int
    {
        $ahora = $ahora ?? Carbon::now();

        $vencidas = 0;

        foreach (TurnoVacante::vivas()->get() as $vacante) {
            if ($vacante->fin()->gte($ahora)) {
                continue;
            }

            $vacante->tv_estado = TurnoVacante::VENCIDA;
            $vacante->save();
            $vacante->postulaciones()->vigentes()->update(['tp_estado' => TurnoPostulacion::RECHAZADA]);
            $vencidas++;
        }

        return $vencidas;
    }

    // ── Postulación ──

    /**
     * Guardias a los que se les puede ofrecer esta vacante.
     *
     * @return Collection<int, object>
     */
    public function elegibles(TurnoVacante $vacante): Collection
    {
        $locales = $this->localesDelAlcance($vacante);

        if (empty($locales)) {
            return collect();
        }

        $candidatos = DB::table('users as u')
            ->join('user_has_institucion as ui', 'ui.ui_usu_id', '=', 'u.id')
            ->join('user_has_roles as ur', 'ur.user_id', '=', 'u.id')
            ->join('roles as r', 'r.id', '=', 'ur.role_id')
            ->where('u.usu_state', 1)
            ->where('u.usu_acepta_extras', true)
            ->where('ui.ui_state', 1)
            ->whereIn('ui.ui_ins_code', $locales)
            ->where('r.name', 'Vigilante')
            ->when($vacante->tv_usu_id_ausente, fn ($q, $id) => $q->where('u.id', '!=', $id))
            ->select('u.id', 'u.usu_nmbcom', 'u.usu_cedula')
            ->distinct()
            ->get();

        if ($candidatos->isEmpty()) {
            return $candidatos;
        }

        return $candidatos->filter(
            fn ($u) => $this->puedeCubrir($vacante, (int) $u->id)
        )->values();
    }

    /**
     * Si este guardia puede tomar la vacante sin quedar en dos lugares a la vez
     * ni sin descanso. Son las mismas reglas que valida el cuadrante.
     */
    public function puedeCubrir(TurnoVacante $vacante, int $usuarioId): bool
    {
        return $this->motivoParaNoCubrir($vacante, $usuarioId) === null;
    }

    /** @return string|null el motivo, o null si sí puede */
    public function motivoParaNoCubrir(TurnoVacante $vacante, int $usuarioId): ?string
    {
        if ((int) $vacante->tv_usu_id_ausente === $usuarioId) {
            return 'Es el turno que usted no cubrió.';
        }

        $locales = $this->localesDelAlcance($vacante);
        $vinculado = DB::table('user_has_institucion')
            ->where('ui_usu_id', $usuarioId)
            ->where('ui_state', 1)
            ->whereIn('ui_ins_code', $locales)
            ->exists();

        if (!$vinculado) {
            return 'No está habilitado para ese local.';
        }

        $inicio = $vacante->inicio();
        $fin    = $vacante->fin();

        $turnos = Turno::where('tu_usu_id', $usuarioId)
            ->where('tu_state', true)
            ->whereIn('tu_estado', ['programado', 'en_curso', 'completado'])
            ->whereBetween('tu_fecha', [
                $inicio->copy()->subDay()->toDateString(),
                $fin->copy()->addDay()->toDateString(),
            ])
            ->get();

        foreach ($turnos as $turno) {
            if ((int) $turno->tu_id === (int) $vacante->tv_turno_id) {
                continue;
            }

            $otroInicio = $this->momento($turno->tu_fecha, $turno->tu_hora_inicio_prevista);
            $otroFin    = $this->fin($otroInicio, $turno->tu_hora_fin_prevista);

            if ($otroInicio->lt($fin) && $inicio->lt($otroFin)) {
                return 'Ya tiene un turno a esa hora.';
            }

            $descanso = $otroFin->lte($inicio)
                ? $otroFin->diffInMinutes($inicio)
                : $fin->diffInMinutes($otroInicio);

            if ($descanso < self::DESCANSO_MINIMO_MINUTOS) {
                return sprintf('Descansaría solo %dh entre turnos.', intdiv($descanso, 60));
            }
        }

        return null;
    }

    /**
     * Registra la postulación. Idempotente: postularse sin señal y sincronizar
     * después no duplica ni falla.
     *
     * @return array{postulacion: TurnoPostulacion, duplicado: bool}
     */
    public function postular(
        TurnoVacante $vacante,
        int $usuarioId,
        ?string $clientUuid = null,
        ?string $ocurridoEn = null
    ): array {
        $existente = TurnoPostulacion::where('tp_tv_id', $vacante->tv_id)
            ->where('tp_usu_id', $usuarioId)
            ->first();

        if ($existente && $existente->tp_estado !== TurnoPostulacion::RETIRADA) {
            return ['postulacion' => $existente, 'duplicado' => true];
        }

        if ($existente) {
            $existente->tp_estado = TurnoPostulacion::POSTULADO;
            $existente->tp_ocurrido_en = $this->sync->ocurridoEn($ocurridoEn);
            $existente->tp_sincronizado_en = $this->sync->sincronizadoEn();
            $existente->save();

            return ['postulacion' => $existente, 'duplicado' => false];
        }

        $postulacion = TurnoPostulacion::create([
            'tp_tv_id'           => $vacante->tv_id,
            'tp_usu_id'          => $usuarioId,
            'tp_estado'          => TurnoPostulacion::POSTULADO,
            'tp_client_uuid'     => $clientUuid,
            'tp_ocurrido_en'     => $this->sync->ocurridoEn($ocurridoEn),
            'tp_sincronizado_en' => $this->sync->sincronizadoEn(),
        ]);

        return ['postulacion' => $postulacion, 'duplicado' => false];
    }

    public function retirar(TurnoVacante $vacante, int $usuarioId): bool
    {
        $postulacion = TurnoPostulacion::where('tp_tv_id', $vacante->tv_id)
            ->where('tp_usu_id', $usuarioId)
            ->vigentes()
            ->first();

        if (!$postulacion) {
            return false;
        }

        $postulacion->tp_estado = TurnoPostulacion::RETIRADA;
        $postulacion->save();

        return true;
    }

    // ── Confirmación ──

    /**
     * El supervisor elige quién cubre.
     *
     * Crea un turno NUEVO, con `tu_plantilla_id` en null a propósito: así
     * republicar el cuadrante no lo borra, igual que no borra los turnos
     * cargados a mano.
     *
     * @return array{turno: Turno, vacante: TurnoVacante}
     */
    public function confirmar(TurnoVacante $vacante, int $postulacionId, ?int $confirmadorId = null): array
    {
        return DB::transaction(function () use ($vacante, $postulacionId, $confirmadorId) {
            // Dos supervisores confirmando a la vez no pueden crear dos turnos.
            $vacante = TurnoVacante::where('tv_id', $vacante->tv_id)->lockForUpdate()->first();

            if ($vacante->tv_estado !== TurnoVacante::ABIERTA) {
                throw new \RuntimeException('La vacante ya no está abierta.');
            }

            $postulacion = TurnoPostulacion::where('tp_id', $postulacionId)
                ->where('tp_tv_id', $vacante->tv_id)
                ->firstOrFail();

            $motivo = $this->motivoParaNoCubrir($vacante, (int) $postulacion->tp_usu_id);
            if ($motivo !== null) {
                throw new \RuntimeException($motivo);
            }

            $turno = Turno::create([
                'tu_ins_code'             => $vacante->tv_ins_code,
                'tu_usu_id'               => $postulacion->tp_usu_id,
                'tu_puesto_id'            => $vacante->tv_puesto_id,
                'tu_plantilla_id'         => null,
                'tu_fecha'                => $vacante->tv_fecha->toDateString(),
                'tu_hora_inicio_prevista' => $vacante->tv_hora_inicio,
                'tu_hora_fin_prevista'    => $vacante->tv_hora_fin,
                'tu_estado'               => 'programado',
                'tu_state'                => true,
                'tu_observaciones'        => 'Cobertura de la vacante #' . $vacante->tv_id,
                'tu_created_user'         => $confirmadorId,
            ]);

            $postulacion->tp_estado = TurnoPostulacion::ACEPTADA;
            $postulacion->save();

            TurnoPostulacion::where('tp_tv_id', $vacante->tv_id)
                ->where('tp_id', '!=', $postulacion->tp_id)
                ->vigentes()
                ->update(['tp_estado' => TurnoPostulacion::RECHAZADA]);

            $vacante->tv_estado = TurnoVacante::CUBIERTA;
            $vacante->tv_turno_cobertura_id = $turno->tu_id;
            $vacante->tv_confirmada_por = $confirmadorId;
            $vacante->tv_confirmada_en = Carbon::now();
            $vacante->save();

            $this->marcarAusencia($vacante, $confirmadorId);

            return ['turno' => $turno, 'vacante' => $vacante];
        });
    }

    /**
     * Deja constancia de la falta en el turno original.
     *
     * No se toca el turno si el guardia ya había marcado: puede ser un marcaje
     * offline que llegó tarde, y en ese caso no faltó.
     */
    private function marcarAusencia(TurnoVacante $vacante, ?int $usuarioId): void
    {
        if ($vacante->tv_motivo === 'refuerzo' || !$vacante->tv_turno_id) {
            return;
        }

        $turno = Turno::find($vacante->tv_turno_id);

        if (!$turno || $turno->tu_marcada_entrada || $turno->tu_estado !== 'programado') {
            return;
        }

        $turno->tu_estado = 'ausente';
        $turno->tu_observaciones = 'Cubierto por otro guardia (vacante #' . $vacante->tv_id . ')';
        $turno->tu_updated_user = $usuarioId;
        $turno->save();
    }

    // ── Apoyo ──

    /**
     * Locales a los que llega esta vacante según la ola en que esté.
     *
     * @return int[]
     */
    public function localesDelAlcance(TurnoVacante $vacante): array
    {
        $local = (int) $vacante->tv_ins_code;

        if ($vacante->tv_alcance !== TurnoVacante::ALCANCE_CIUDAD) {
            return [$local];
        }

        $ciudad = DB::table('organizacion_institucion')->where('ins_code', $local)->value('ins_cd_id');

        // Un local sin ciudad no puede escalar: no se sabe con quién comparte zona.
        if (!$ciudad) {
            return [$local];
        }

        return DB::table('organizacion_institucion')
            ->where('ins_cd_id', $ciudad)
            ->where('ins_estado', 1)
            ->pluck('ins_code')
            ->map(fn ($c) => (int) $c)
            ->all();
    }

    /** Horas ya trabajadas en el mes, para que el supervisor reparta con criterio. */
    public function horasDelMes(int $usuarioId, ?Carbon $referencia = null): float
    {
        $referencia = $referencia ?? Carbon::now();

        $turnos = Turno::where('tu_usu_id', $usuarioId)
            ->where('tu_state', true)
            ->whereIn('tu_estado', ['programado', 'en_curso', 'completado'])
            ->whereBetween('tu_fecha', [
                $referencia->copy()->startOfMonth()->toDateString(),
                $referencia->copy()->endOfMonth()->toDateString(),
            ])
            ->get();

        $minutos = 0;
        foreach ($turnos as $turno) {
            $inicio = $this->momento($turno->tu_fecha, $turno->tu_hora_inicio_prevista);
            $minutos += $inicio->diffInMinutes($this->fin($inicio, $turno->tu_hora_fin_prevista));
        }

        return round($minutos / 60, 1);
    }

    private function momento($fecha, $hora): Carbon
    {
        $dia = $fecha instanceof Carbon ? $fecha->toDateString() : (string) $fecha;

        return Carbon::parse($dia)->setTimeFromTimeString((string) $hora);
    }

    private function fin(Carbon $inicio, $horaFin): Carbon
    {
        $fin = $inicio->copy()->setTimeFromTimeString((string) $horaFin);

        return $fin->lte($inicio) ? $fin->addDay() : $fin;
    }
}
