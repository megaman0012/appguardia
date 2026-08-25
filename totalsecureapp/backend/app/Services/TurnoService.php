<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Administracion\Models\Turno;

class TurnoService
{
    public function buscarTurnoProgramado(
        int $usuarioId,
        int $institucionId,
        Carbon $fecha
    ): ?Turno {
        return Turno::where('tu_usu_id', $usuarioId)
            ->where('tu_ins_code', $institucionId)
            ->where('tu_fecha', $fecha->toDateString())
            ->where('tu_state', true)
            ->whereIn('tu_estado', ['programado', 'en_curso'])
            ->first();
    }

    /**
     * Turno al que corresponde un marcaje concreto.
     *
     * buscarTurnoProgramado() devuelve el primero que encuentre, que alcanza
     * cuando hay un solo turno en el dia. Si el guardia cubre dos (mañana y
     * noche) hay que elegir bien:
     *
     *   entrada -> el turno 'programado' cuya hora de inicio esta mas cerca del
     *              marcaje. Marcar a las 05:50 debe abrir el de las 06:00, no el
     *              de las 14:00.
     *   salida  -> el que esta 'en_curso', que es el que el guardia abrio.
     *
     * Devuelve null si no hay turno: no todas las instituciones los usan, y el
     * marcaje debe guardarse igual.
     */
    public function buscarTurnoParaMarcaje(
        int $usuarioId,
        int $institucionId,
        Carbon $momento,
        bool $esEntrada
    ): ?Turno {
        $base = Turno::where('tu_usu_id', $usuarioId)
            ->where('tu_ins_code', $institucionId)
            ->where('tu_fecha', $momento->toDateString())
            ->where('tu_state', true);

        if (!$esEntrada) {
            return (clone $base)->where('tu_estado', 'en_curso')
                ->orderByDesc('tu_marcada_entrada')
                ->first();
        }

        $candidatos = (clone $base)->where('tu_estado', 'programado')
            ->orderBy('tu_hora_inicio_prevista')
            ->get();

        if ($candidatos->isEmpty()) {
            return null;
        }

        $minutosMarcaje = (int) $momento->format('H') * 60 + (int) $momento->format('i');

        return $candidatos->sortBy(function (Turno $turno) use ($minutosMarcaje) {
            [$h, $m] = array_pad(explode(':', (string) $turno->tu_hora_inicio_prevista), 2, 0);
            return abs(((int) $h * 60 + (int) $m) - $minutosMarcaje);
        })->first();
    }

    public function vincularEntrada(
        Turno $turno,
        int $biometriaCode,
        Carbon $fechaMarcacion
    ): Turno {
        $turno->tu_bio_entrada_code = $biometriaCode;
        $turno->tu_marcada_entrada = $fechaMarcacion;
        $turno->tu_estado = 'en_curso';
        $turno->tu_minutos_tardanza = $this->calcularTardanza(
            $turno->tu_hora_inicio_prevista,
            $fechaMarcacion
        );
        $turno->save();
        return $turno;
    }

    public function vincularSalida(
        Turno $turno,
        int $biometriaCode,
        Carbon $fechaMarcacion
    ): Turno {
        $turno->tu_bio_salida_code = $biometriaCode;
        $turno->tu_marcada_salida = $fechaMarcacion;
        $turno->tu_estado = 'completado';
        $turno->tu_minutos_extras = $this->calcularMinutosExtras(
            $turno->tu_hora_fin_prevista,
            $fechaMarcacion
        );
        $turno->save();
        return $turno;
    }

    public function calcularTardanza(
        string $horaInicioPrevista,
        Carbon $fechaMarcacion
    ): int {
        // Compara solo la hora del dia para evitar contaminacion por fecha
        $minutosInicio = $this->minutosDelDia($horaInicioPrevista);
        $minutosMarcacion = $fechaMarcacion->hour * 60 + $fechaMarcacion->minute;

        return max(0, $minutosMarcacion - $minutosInicio);
    }

    public function calcularMinutosExtras(
        string $horaFinPrevista,
        Carbon $fechaMarcacion
    ): int {
        // Compara solo la hora del dia para evitar contaminacion por fecha
        $minutosFin = $this->minutosDelDia($horaFinPrevista);
        $minutosMarcacion = $fechaMarcacion->hour * 60 + $fechaMarcacion->minute;

        return max(0, $minutosMarcacion - $minutosFin);
    }

    private function minutosDelDia(string $hora): int
    {
        $t = Carbon::parse($hora);

        return $t->hour * 60 + $t->minute;
    }

    public function cerrarTurnosSinMarcacion(int $institucionId): int
    {
        $hoy = Carbon::today()->toDateString();
        $ahora = Carbon::now();

        // Cerrar turnos de dias anteriores o de hoy cuya hora de inicio ya paso
        $turnos = Turno::where('tu_ins_code', $institucionId)
            ->where('tu_fecha', '<=', $hoy)
            ->where('tu_estado', 'programado')
            ->where(function ($query) use ($ahora, $hoy) {
                $query->where('tu_fecha', '<', $hoy)
                    ->orWhere('tu_hora_inicio_prevista', '<', $ahora->format('H:i:s'));
            })
            ->get();

        $count = 0;
        foreach ($turnos as $turno) {
            $turno->tu_estado = 'ausente';
            $turno->tu_observaciones = 'Marcado como ausente por sistema - no marco entrada';
            $turno->tu_updated_user = 0; // Sistema
            $turno->save();
            $count++;
        }

        return $count;
    }
}
