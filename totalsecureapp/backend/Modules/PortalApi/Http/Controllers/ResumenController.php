<?php

namespace Modules\PortalApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Administracion\Models\Acceso;
use Modules\Administracion\Models\Alertas;
use Modules\Administracion\Models\Novedad;
use Modules\Administracion\Models\ronda_cabecera;
use Modules\Administracion\Models\user_has_biometria;

class ResumenController extends PortalController
{
    /**
     * GET api/portal/resumen
     *
     * Totales del rango para el tablero del portal, para no obligar al cliente a
     * paginar cinco listados solo para contar.
     */
    public function index(Request $request): JsonResponse
    {
        list($error, $instituciones, $desde, $hasta) = $this->contexto($request);
        if ($error) {
            return $error;
        }

        $inicio = $desde . ' 00:00:00';
        $fin    = $hasta . ' 23:59:59';

        $alertas = Alertas::forInstitutions($instituciones)
            ->whereBetween('al_fecha', [$inicio, $fin])
            ->where('al_estado', 1);

        $accesos = Acceso::forInstitutions($instituciones)
            ->whereBetween('ac_created_at', [$inicio, $fin])
            ->where('ac_estado', true);

        return response()->json([
            'rango'         => compact('desde', 'hasta'),
            'instituciones' => $instituciones,
            'totales'       => [
                'marcajes'  => user_has_biometria::forInstitutions($instituciones)
                    ->whereBetween('bio_created_at', [$inicio, $fin])
                    ->where('bio_state', true)
                    ->count(),
                'rondas'    => ronda_cabecera::forInstitutions($instituciones)
                    ->whereBetween('rc_fecha_inicio', [$inicio, $fin])
                    ->where('rc_estado', 1)
                    ->count(),
                'novedades' => Novedad::forInstitutions($instituciones)
                    ->whereBetween('nv_fecha_hora', [$inicio, $fin])
                    ->where('nv_estado', 1)
                    ->count(),
                'accesos'   => (clone $accesos)->count(),
                'accesos_en_curso' => (clone $accesos)->estado(Acceso::ESTADO_EN_CURSO)->count(),
                'alertas'   => (clone $alertas)->count(),
                'alertas_pendientes' => (clone $alertas)->pendientes()->count(),
            ],
        ]);
    }
}
