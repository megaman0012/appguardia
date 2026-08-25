<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Administracion\Models\Plantilla;
use Modules\Administracion\Models\PlantillaFranja;
use Modules\Administracion\Models\Turno;
use Modules\Administracion\Models\UserHasInstitucion;

/**
 * Valida un cuadrante y genera los turnos del periodo.
 *
 * El valor no esta en crear filas: eso es un bucle. Esta en lo que se revisa
 * ANTES de crearlas, que es lo que una planilla de Excel no puede hacer.
 */
class PlantillaTurnoService
{
    /** Minutos de descanso por debajo de los cuales se avisa. */
    private const DESCANSO_MINIMO_MINUTOS = 8 * 60;

    /**
     * Revisa la plantilla sin tocar nada.
     *
     * @return array{errores: string[], avisos: string[]}
     */
    public function validar(Plantilla $plantilla, Carbon $desde, Carbon $hasta): array
    {
        $errores = [];
        $avisos  = [];

        $franjas = $plantilla->franjas()->with(['puesto', 'asignaciones.usuario'])->get();

        if ($franjas->isEmpty()) {
            $errores[] = 'La plantilla no tiene franjas definidas.';
            return compact('errores', 'avisos');
        }

        $this->revisarPuestos($plantilla, $franjas, $errores);
        $this->revisarCobertura($franjas, $avisos);
        $this->revisarVinculoConElLocal($plantilla, $franjas, $errores);
        $this->revisarSolapesYDescanso($plantilla, $franjas, $desde, $hasta, $errores, $avisos);

        return compact('errores', 'avisos');
    }

    /**
     * Genera los turnos del periodo.
     *
     * Reemplaza solo los que genero ESTA plantilla y que todavia no tienen
     * marcaje. Nunca toca:
     *   - los turnos cargados a mano (tu_plantilla_id null),
     *   - los que el guardia ya marco, aunque la plantilla haya cambiado.
     * Rehacer el cuadrante a mitad de mes no puede borrar lo que ya ocurrio.
     *
     * @return array{creados: int, conservados: int, errores: string[], avisos: string[]}
     */
    public function generar(Plantilla $plantilla, Carbon $desde, Carbon $hasta): array
    {
        $revision = $this->validar($plantilla, $desde, $hasta);

        if (!empty($revision['errores'])) {
            return $revision + ['creados' => 0, 'conservados' => 0];
        }

        $franjas = $plantilla->franjas()->with('asignaciones')->where('pf_estado', true)->get();

        return DB::transaction(function () use ($plantilla, $franjas, $desde, $hasta, $revision) {
            $yaMarcados = Turno::where('tu_plantilla_id', $plantilla->pl_id)
                ->whereBetween('tu_fecha', [$desde->toDateString(), $hasta->toDateString()])
                ->where(function ($q) {
                    $q->whereNotNull('tu_marcada_entrada')->orWhereNotNull('tu_marcada_salida');
                })
                ->get();

            $conservados = $yaMarcados->count();

            // Se limpian solo los generados por esta plantilla y sin marcaje.
            Turno::where('tu_plantilla_id', $plantilla->pl_id)
                ->whereBetween('tu_fecha', [$desde->toDateString(), $hasta->toDateString()])
                ->whereNull('tu_marcada_entrada')
                ->whereNull('tu_marcada_salida')
                ->delete();

            $ocupados = $yaMarcados
                ->map(fn (Turno $t) => $t->tu_usu_id . '|' . $t->tu_fecha->toDateString() . '|' . $t->tu_hora_inicio_prevista)
                ->flip();

            $creados = 0;

            foreach ($this->fechas($desde, $hasta) as $fecha) {
                foreach ($franjas as $franja) {
                    if ($franja->pf_dia_semana !== $fecha->dayOfWeekIso) {
                        continue;
                    }

                    foreach ($franja->asignaciones as $asignacion) {
                        if (!$asignacion->vigenteEn($fecha)) {
                            continue;
                        }

                        $clave = $asignacion->pa_usu_id . '|' . $fecha->toDateString() . '|' . $franja->pf_hora_inicio;
                        if ($ocupados->has($clave)) {
                            continue; // ya existe y esta marcado
                        }

                        Turno::create([
                            'tu_ins_code'             => $plantilla->pl_ins_code,
                            'tu_usu_id'               => $asignacion->pa_usu_id,
                            'tu_puesto_id'            => $franja->pf_puesto_id,
                            'tu_plantilla_id'         => $plantilla->pl_id,
                            'tu_fecha'                => $fecha->toDateString(),
                            'tu_hora_inicio_prevista' => $franja->pf_hora_inicio,
                            'tu_hora_fin_prevista'    => $franja->pf_hora_fin,
                            'tu_estado'               => 'programado',
                            'tu_state'                => true,
                        ]);
                        $creados++;
                    }
                }
            }

            $plantilla->pl_estado = Plantilla::PUBLICADA;
            $plantilla->save();

            return $revision + compact('creados', 'conservados');
        });
    }

    // ── Validaciones ──

    private function revisarPuestos(Plantilla $plantilla, $franjas, array &$errores): void
    {
        foreach ($franjas as $franja) {
            $puesto = $franja->puesto;

            if (!$puesto) {
                $errores[] = "Una franja apunta a un puesto que ya no existe.";
                continue;
            }

            // Un puesto de otro local produciria un turno incoherente.
            if ((int) $puesto->pu_ins_code !== (int) $plantilla->pl_ins_code) {
                $errores[] = sprintf(
                    'El puesto "%s" pertenece a otro local.',
                    $puesto->pu_nombre
                );
            }
        }
    }

    private function revisarCobertura($franjas, array &$avisos): void
    {
        foreach ($franjas as $franja) {
            if ($franja->asignaciones->where('pa_estado', true)->isEmpty()) {
                $avisos[] = sprintf(
                    'Sin cubrir: %s, %s.',
                    optional($franja->puesto)->pu_nombre ?? 'puesto',
                    $franja->descripcion
                );
            }
        }
    }

    private function revisarVinculoConElLocal(Plantilla $plantilla, $franjas, array &$errores): void
    {
        $usuarios = $franjas->flatMap->asignaciones->pluck('pa_usu_id')->unique();

        if ($usuarios->isEmpty()) {
            return;
        }

        $vinculados = UserHasInstitucion::whereIn('ui_usu_id', $usuarios)
            ->where('ui_ins_code', $plantilla->pl_ins_code)
            ->where('ui_state', 1)
            ->pluck('ui_usu_id')
            ->flip();

        foreach ($franjas as $franja) {
            foreach ($franja->asignaciones as $asignacion) {
                if ($vinculados->has($asignacion->pa_usu_id)) {
                    continue;
                }
                // Sin el vinculo no podria ni marcar: la app lo rechazaria.
                $errores[] = sprintf(
                    '%s no está vinculado a este local, no podría marcar.',
                    optional($asignacion->usuario)->usu_nmbcom ?? "Usuario {$asignacion->pa_usu_id}"
                );
            }
        }
    }

    /**
     * Solapes del mismo guardia y descansos demasiado cortos.
     *
     * `turno` no tiene ninguna restriccion que impida programar a alguien en dos
     * lugares a la vez, asi que se revisa aqui.
     */
    private function revisarSolapesYDescanso(
        Plantilla $plantilla,
        $franjas,
        Carbon $desde,
        Carbon $hasta,
        array &$errores,
        array &$avisos
    ): void {
        $porGuardia = [];

        foreach ($this->fechas($desde, $hasta) as $fecha) {
            foreach ($franjas as $franja) {
                if ($franja->pf_dia_semana !== $fecha->dayOfWeekIso) {
                    continue;
                }
                foreach ($franja->asignaciones as $asignacion) {
                    if (!$asignacion->vigenteEn($fecha)) {
                        continue;
                    }

                    $inicio = $fecha->copy()->setTimeFromTimeString((string) $franja->pf_hora_inicio);
                    $fin    = $fecha->copy()->setTimeFromTimeString((string) $franja->pf_hora_fin);
                    if ($franja->cruzaMedianoche()) {
                        $fin->addDay();
                    }

                    $porGuardia[$asignacion->pa_usu_id][] = [
                        'inicio'  => $inicio,
                        'fin'     => $fin,
                        'nombre'  => optional($asignacion->usuario)->usu_nmbcom ?? "Usuario {$asignacion->pa_usu_id}",
                        'detalle' => sprintf('%s %s', $fecha->format('d/m'), $franja->descripcion),
                    ];
                }
            }
        }

        foreach ($porGuardia as $bloques) {
            usort($bloques, fn ($a, $b) => $a['inicio'] <=> $b['inicio']);

            for ($i = 1; $i < count($bloques); $i++) {
                $previo = $bloques[$i - 1];
                $actual = $bloques[$i];

                if ($actual['inicio']->lt($previo['fin'])) {
                    $errores[] = sprintf(
                        '%s quedaría en dos turnos a la vez (%s y %s).',
                        $actual['nombre'], $previo['detalle'], $actual['detalle']
                    );
                    continue;
                }

                $descanso = $previo['fin']->diffInMinutes($actual['inicio']);
                if ($descanso < self::DESCANSO_MINIMO_MINUTOS) {
                    $avisos[] = sprintf(
                        '%s descansaría %dh entre %s y %s.',
                        $actual['nombre'], intdiv($descanso, 60), $previo['detalle'], $actual['detalle']
                    );
                }
            }
        }

        $errores = array_values(array_unique($errores));
        $avisos  = array_values(array_unique($avisos));
    }

    /** @return \Generator<Carbon> */
    private function fechas(Carbon $desde, Carbon $hasta): \Generator
    {
        $cursor = $desde->copy()->startOfDay();
        $limite = $hasta->copy()->startOfDay();

        while ($cursor->lte($limite)) {
            yield $cursor->copy();
            $cursor->addDay();
        }
    }
}
