<?php

namespace App\Services;

use Modules\Administracion\Models\Alertas;
use Modules\Administracion\Models\AlertaDetalle;
use Modules\Administracion\Models\AlertaHistorial;
use Modules\Acceso\Models\users;
use Illuminate\Support\Facades\DB;

class AlertaService
{
    public function crearAlerta(array $datos): Alertas
    {
        return DB::transaction(function () use ($datos) {
            $alerta = Alertas::create([
                'al_ins_code' => $datos['institucion_id'],
                'al_usu_id' => $datos['usuario_id'],
                'al_lat' => $datos['lat'],
                'al_lng' => $datos['lng'],
                'al_anio' => date('Y'),
                'al_estado_alerta' => 'pendiente',
                'al_estado' => 1,
                'al_prioridad' => $datos['prioridad'] ?? 'media',
                'al_observacion' => $datos['observacion'],
                'al_created_user' => $datos['usuario_id'],
            ]);

            AlertaHistorial::registrar(
                $alerta->al_code,
                'creada',
                $datos['usuario_id'],
                'Alerta creada por usuario ID: ' . $datos['usuario_id']
            );

            $this->asignarASupervisor($alerta);

            event(new \App\Events\AlertaCreada($alerta));

            return $alerta;
        });
    }

    public function asignarASupervisor(Alertas $alerta): ?AlertaDetalle
    {
        $supervisor = users::whereHas('roles', function ($q) {
            $q->where('name', 'Supervisor')
              ->where('estado', 1);
        })
        ->whereHas('instituciones', function ($q) use ($alerta) {
            $q->where('ins_code', $alerta->al_ins_code);
        })
        ->first();

        if (!$supervisor) {
            return null;
        }

        $detalle = AlertaDetalle::create([
            'ad_al_code' => $alerta->al_code,
            'ad_usuario_asignado' => $supervisor->id,
            'ad_prioridad' => $alerta->al_prioridad,
            'ad_estado' => 'asignada',
            'ad_fecha_asignacion' => now(),
            'ad_created_user' => $alerta->al_created_user,
        ]);

        $alerta->update(['al_estado_alerta' => 'en_atencion']);

        AlertaHistorial::registrar(
            $alerta->al_code,
            'asignada',
            $alerta->al_created_user,
            "Asignada a supervisor: {$supervisor->name}"
        );

        return $detalle;
    }

    public function atenderAlerta(
        Alertas $alerta,
        int $usuarioId,
        string $observacion
    ): AlertaDetalle {
        return DB::transaction(function () use ($alerta, $usuarioId, $observacion) {
            $detalle = $alerta->asignacionActual;

            if (!$detalle) {
                throw new \Exception('La alerta no tiene asignación activa');
            }

            $detalle->marcarResuelta($observacion);

            AlertaHistorial::registrar(
                $alerta->al_code,
                'atendida',
                $usuarioId,
                "Alerta atendida. Tiempo: {$detalle->ad_tiempo_respuesta_seg} segundos"
            );

            return $detalle;
        });
    }

    public function escalarAlerta(
        Alertas $alerta,
        int $usuarioId,
        string $motivo
    ): void {
        DB::transaction(function () use ($alerta, $usuarioId, $motivo) {
            $detalle = $alerta->asignacionActual;
            if ($detalle) {
                $detalle->update(['ad_estado' => 'escalada']);
            }

            $nivelActual = $alerta->nivel_escalamiento;
            $nuevoSupervisor = $this->buscarSupervisorPorNivel(
                $alerta->al_ins_code,
                $nivelActual
            );

            if ($nuevoSupervisor) {
                AlertaDetalle::create([
                    'ad_al_code' => $alerta->al_code,
                    'ad_usuario_asignado' => $nuevoSupervisor->id,
                    'ad_prioridad' => $alerta->al_prioridad,
                    'ad_estado' => 'asignada',
                    'ad_fecha_asignacion' => now(),
                    'ad_created_user' => $usuarioId,
                ]);

                AlertaHistorial::registrar(
                    $alerta->al_code,
                    'escalada',
                    $usuarioId,
                    "Escalada a nivel {$nivelActual}: {$motivo}"
                );
            }
        });
    }

    public function cancelarAlerta(
        Alertas $alerta,
        int $usuarioId,
        string $motivo
    ): void {
        DB::transaction(function () use ($alerta, $usuarioId, $motivo) {
            $alerta->update(['al_estado_alerta' => 'cancelada']);

            AlertaHistorial::registrar(
                $alerta->al_code,
                'cancelada',
                $usuarioId,
                $motivo
            );
        });
    }

    public function obtenerAlertasActivas(int $insCode): \Illuminate\Database\Eloquent\Collection
    {
        return Alertas::porInstitucion($insCode)
            ->whereIn('al_estado_alerta', ['pendiente', 'en_atencion'])
            ->with(['usuario', 'asignacionActual.usuarioAsignado'])
            ->orderBy('al_prioridad', 'desc')
            ->orderBy('al_fecha', 'asc')
            ->get();
    }

    public function obtenerEstadisticas(int $insCode, string $periodo = 'hoy'): array
    {
        $query = Alertas::porInstitucion($insCode);

        $query = match($periodo) {
            'hoy' => $query->delDia(),
            'semana' => $query->whereBetween('al_fecha', [now()->startOfWeek(), now()->endOfWeek()]),
            'mes' => $query->whereMonth('al_fecha', now()->month),
            default => $query->delDia(),
        };

        return [
            'total' => $query->count(),
            'pendientes' => (clone $query)->pendientes()->count(),
            'en_atencion' => (clone $query)->enAtencion()->count(),
            'finalizadas' => (clone $query)->where('al_estado_alerta', 'finalizada')->count(),
            'por_prioridad' => [
                'critica' => (clone $query)->porPrioridad('critica')->count(),
                'alta' => (clone $query)->porPrioridad('alta')->count(),
                'media' => (clone $query)->porPrioridad('media')->count(),
                'baja' => (clone $query)->porPrioridad('baja')->count(),
            ],
            'tiempo_respuesta_promedio' => $this->calcularTiempoRespuestaPromedio($insCode, $periodo),
        ];
    }

    private function buscarSupervisorPorNivel(int $insCode, int $nivel): ?users
    {
        $rolNivel = match($nivel) {
            1 => 'Gerente',
            2 => 'Director',
            default => 'Supervisor',
        };

        return users::whereHas('roles', function ($q) use ($rolNivel) {
            $q->where('name', $rolNivel)->where('estado', 1);
        })
        ->whereHas('instituciones', function ($q) use ($insCode) {
            $q->where('ins_code', $insCode);
        })
        ->first();
    }

    private function calcularTiempoRespuestaPromedio(int $insCode, string $periodo): float
    {
        $query = Alertas::porInstitucion($insCode)
            ->where('al_estado_alerta', 'finalizada');

        $query = match($periodo) {
            'hoy' => $query->delDia(),
            'semana' => $query->whereBetween('al_fecha', [now()->startOfWeek(), now()->endOfWeek()]),
            'mes' => $query->whereMonth('al_fecha', now()->month),
            default => $query->delDia(),
        };

        $alertas = $query->get();

        if ($alertas->isEmpty()) {
            return 0;
        }

        $totalSegundos = $alertas->sum(function ($alerta) {
            $detalle = $alerta->detalle()->whereNotNull('ad_fecha_atencion')->first();
            return $detalle ? $detalle->ad_tiempo_respuesta_seg : 0;
        });

        return $totalSegundos / $alertas->count();
    }
}
