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
        list($error, $ctx) = $this->contexto($request);
        if ($error) {
            return $error;
        }

        $alertas = $ctx->consulta(Alertas::class, 'al_fecha')->where('al_estado', 1);
        $accesos = $ctx->consulta(Acceso::class, 'ac_created_at')->where('ac_estado', true);

        return response()->json([
            'rango'         => $ctx->rango(),
            'instituciones' => $ctx->instituciones(),
            'totales'       => [
                'marcajes'  => $ctx->consulta(user_has_biometria::class, 'bio_created_at')
                    ->where('bio_state', true)->count(),
                'rondas'    => $ctx->consulta(ronda_cabecera::class, 'rc_fecha_inicio')
                    ->where('rc_estado', 1)->count(),
                'novedades' => $ctx->consulta(Novedad::class, 'nv_fecha_hora')
                    ->where('nv_estado', 1)->count(),
                'accesos'            => (clone $accesos)->count(),
                'accesos_en_curso'   => (clone $accesos)->estado(Acceso::ESTADO_EN_CURSO)->count(),
                'alertas'            => (clone $alertas)->count(),
                'alertas_pendientes' => (clone $alertas)->pendientes()->count(),
            ],
        ]);
    }
}
