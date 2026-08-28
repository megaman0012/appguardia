<?php

namespace App\Services;

use Modules\Administracion\Models\Plantilla;
use Modules\Administracion\Models\PlantillaFranja;

/**
 * Arma el cuadrante semanal para verlo de un vistazo.
 *
 * Una lista de franjas ordenada por día no deja ver lo que importa: dónde queda
 * un puesto sin cubrir y dónde un guardia está en dos lugares a la vez. Esas dos
 * cosas se ven en una grilla y no se ven en una lista, y son justamente las que
 * generan una llamada a las seis de la mañana.
 *
 * El patrón es SEMANAL y circular: un turno de domingo 22:00 a 06:00 pisa el
 * lunes de la misma semana, así que los intervalos se calculan en "minutos de la
 * semana" (0 a 10.079) y el que se pasa del final vuelve al principio. Sin eso,
 * el choque más típico del negocio —el relevo de la noche del domingo— no se
 * detectaría nunca.
 */
class CuadranteGrilla
{
    private const MINUTOS_POR_DIA = 1440;
    private const MINUTOS_POR_SEMANA = 7 * self::MINUTOS_POR_DIA;

    /** Descanso mínimo entre turnos, el mismo que valida la generación. */
    private const DESCANSO_MINIMO_MINUTOS = 8 * 60;

    public const SIN_CUBRIR = 'sin_cubrir';
    public const CONFLICTO  = 'conflicto';
    public const DESCANSO   = 'descanso';

    /**
     * @return array{
     *   dias: array<int, string>,
     *   filas: array<int, array{puesto: string, celdas: array<int, array>}>,
     *   guardias: array<int, array>,
     *   resumen: array<string, int>
     * }
     */
    public function armar(Plantilla $plantilla): array
    {
        $franjas = $plantilla->franjas()
            ->with(['puesto', 'asignaciones.usuario'])
            ->orderBy('pf_dia_semana')
            ->orderBy('pf_hora_inicio')
            ->get();

        $problemas = $this->problemasPorFranja($franjas);
        $filas     = $this->filasPorPuesto($franjas, $problemas);

        return [
            'dias'     => PlantillaFranja::DIAS,
            'filas'    => $filas,
            'guardias' => $this->horasPorGuardia($franjas),
            'resumen'  => $this->resumen($franjas, $problemas),
        ];
    }

    // ── Detección de problemas ──

    /**
     * Qué problema tiene cada franja.
     *
     * @return array<int, array<int, string>> pf_id => [motivos]
     */
    private function problemasPorFranja($franjas): array
    {
        $problemas = [];

        foreach ($franjas as $franja) {
            $problemas[$franja->pf_id] = [];

            // Una franja sin nadie asignado es un puesto que va a amanecer
            // vacío. No es un error de datos, pero hay que verlo.
            if ($this->asignacionesVigentes($franja)->isEmpty()) {
                $problemas[$franja->pf_id][] = self::SIN_CUBRIR;
            }
        }

        foreach ($this->intervalosPorGuardia($franjas) as $intervalos) {
            $this->marcarChoques($intervalos, $problemas);
        }

        return $problemas;
    }

    /**
     * Intervalos de cada guardia en minutos de la semana.
     *
     * @return array<int, array<int, array{inicio: int, fin: int, franja: int}>>
     */
    private function intervalosPorGuardia($franjas): array
    {
        $porGuardia = [];

        foreach ($franjas as $franja) {
            $inicio = $this->minutoDeLaSemana($franja);
            $duracion = $this->duracion($franja);

            foreach ($this->asignacionesVigentes($franja) as $asignacion) {
                $porGuardia[$asignacion->pa_usu_id][] = [
                    'inicio' => $inicio,
                    'fin'    => $inicio + $duracion,
                    'franja' => $franja->pf_id,
                ];
            }
        }

        return $porGuardia;
    }

    /**
     * Marca solapes y descansos cortos de un mismo guardia.
     *
     * @param array<int, array{inicio: int, fin: int, franja: int}> $intervalos
     * @param array<int, array<int, string>> $problemas
     */
    private function marcarChoques(array $intervalos, array &$problemas): void
    {
        usort($intervalos, fn ($a, $b) => $a['inicio'] <=> $b['inicio']);
        $total = count($intervalos);

        if ($total < 2) {
            return;
        }

        for ($i = 0; $i < $total; $i++) {
            $actual = $intervalos[$i];
            $siguiente = $intervalos[($i + 1) % $total];

            // El último se compara con el primero de la semana siguiente: es el
            // relevo del domingo a la noche, el choque más común de todos.
            $inicioSiguiente = $siguiente['inicio'] + ($i + 1 >= $total ? self::MINUTOS_POR_SEMANA : 0);

            if ($inicioSiguiente < $actual['fin']) {
                $problemas[$actual['franja']][] = self::CONFLICTO;
                $problemas[$siguiente['franja']][] = self::CONFLICTO;
                continue;
            }

            if ($inicioSiguiente - $actual['fin'] < self::DESCANSO_MINIMO_MINUTOS) {
                $problemas[$actual['franja']][] = self::DESCANSO;
                $problemas[$siguiente['franja']][] = self::DESCANSO;
            }
        }
    }

    // ── Armado de la grilla ──

    /** @return array<int, array{puesto: string, celdas: array<int, array>}> */
    private function filasPorPuesto($franjas, array $problemas): array
    {
        $filas = [];

        foreach ($franjas as $franja) {
            $puestoId = $franja->pf_puesto_id ?? 0;

            if (!isset($filas[$puestoId])) {
                $filas[$puestoId] = [
                    'puesto' => optional($franja->puesto)->pu_nombre ?? 'Sin puesto',
                    'celdas' => array_fill_keys(array_keys(PlantillaFranja::DIAS), []),
                ];
            }

            $motivos = array_values(array_unique($problemas[$franja->pf_id] ?? []));

            $filas[$puestoId]['celdas'][$franja->pf_dia_semana][] = [
                'pf_id'    => $franja->pf_id,
                'horario'  => substr((string) $franja->pf_hora_inicio, 0, 5)
                    . '–' . substr((string) $franja->pf_hora_fin, 0, 5),
                'cruza'    => $franja->cruzaMedianoche(),
                'guardias' => $this->asignacionesVigentes($franja)
                    ->map(fn ($a) => optional($a->usuario)->usu_nmbcom ?? "Usuario {$a->pa_usu_id}")
                    ->values()
                    ->all(),
                'motivos'  => $motivos,
                'estado'   => $this->estadoDe($motivos),
            ];
        }

        return array_values($filas);
    }

    /** El problema más grave manda el color de la celda. */
    private function estadoDe(array $motivos): string
    {
        foreach ([self::CONFLICTO, self::SIN_CUBRIR, self::DESCANSO] as $grave) {
            if (in_array($grave, $motivos, true)) {
                return $grave;
            }
        }

        return 'ok';
    }

    /**
     * Horas semanales de cada guardia.
     *
     * Es el dato que evita el cuadrante donde uno hace 72 horas y otro 24 sin
     * que nadie lo note hasta la planilla.
     *
     * @return array<int, array{nombre: string, horas: float, turnos: int}>
     */
    private function horasPorGuardia($franjas): array
    {
        $guardias = [];

        foreach ($franjas as $franja) {
            $horas = $this->duracion($franja) / 60;

            foreach ($this->asignacionesVigentes($franja) as $asignacion) {
                $id = $asignacion->pa_usu_id;

                if (!isset($guardias[$id])) {
                    $guardias[$id] = [
                        'nombre' => optional($asignacion->usuario)->usu_nmbcom ?? "Usuario {$id}",
                        'horas'  => 0,
                        'turnos' => 0,
                    ];
                }

                $guardias[$id]['horas'] += $horas;
                $guardias[$id]['turnos']++;
            }
        }

        foreach ($guardias as &$guardia) {
            $guardia['horas'] = round($guardia['horas'], 1);
        }

        uasort($guardias, fn ($a, $b) => $b['horas'] <=> $a['horas']);

        return $guardias;
    }

    /** @return array<string, int> */
    private function resumen($franjas, array $problemas): array
    {
        $contar = fn (string $motivo) => count(array_filter(
            $problemas,
            fn ($motivos) => in_array($motivo, $motivos, true)
        ));

        $minutos = 0;
        foreach ($franjas as $franja) {
            $minutos += $this->duracion($franja) * $this->asignacionesVigentes($franja)->count();
        }

        return [
            'franjas'    => $franjas->count(),
            'sin_cubrir' => $contar(self::SIN_CUBRIR),
            'conflictos' => $contar(self::CONFLICTO),
            'descansos'  => $contar(self::DESCANSO),
            'horas'      => (int) round($minutos / 60),
        ];
    }

    // ── Apoyo ──

    private function asignacionesVigentes(PlantillaFranja $franja)
    {
        return $franja->asignaciones->where('pa_estado', true);
    }

    private function minutoDeLaSemana(PlantillaFranja $franja): int
    {
        return ($franja->pf_dia_semana - 1) * self::MINUTOS_POR_DIA
            + $this->minutos($franja->pf_hora_inicio);
    }

    /** Duración real, contemplando el cruce de medianoche. */
    private function duracion(PlantillaFranja $franja): int
    {
        $inicio = $this->minutos($franja->pf_hora_inicio);
        $fin    = $this->minutos($franja->pf_hora_fin);
        $duracion = ($fin - $inicio + self::MINUTOS_POR_DIA) % self::MINUTOS_POR_DIA;

        // Entrada y salida iguales: se interpreta como turno de 24 horas, que es
        // lo único que puede significar en un patrón semanal.
        return $duracion === 0 ? self::MINUTOS_POR_DIA : $duracion;
    }

    private function minutos(?string $hora): int
    {
        [$h, $m] = array_pad(explode(':', (string) $hora), 2, 0);

        return ((int) $h) * 60 + ((int) $m);
    }
}
