<?php

namespace Modules\PortalApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Administracion\Models\Acceso;
use Modules\Administracion\Models\Alertas;
use Modules\Administracion\Models\Novedad;
use Modules\Administracion\Models\ronda_cabecera;
use Modules\Administracion\Models\user_has_biometria;

/**
 * Reporteria de solo lectura del portal cliente.
 *
 * Todos los listados salen del mismo dominio que usa la app movil y el panel: no
 * hay consultas propias ni tablas nuevas, solo lectura acotada por institucion.
 */
class ReporteController extends PortalController
{
    /**
     * GET api/portal/biometria — marcajes de entrada/salida del personal.
     */
    public function biometria(Request $request): JsonResponse
    {
        list($error, $instituciones, $desde, $hasta) = $this->contexto($request);
        if ($error) {
            return $error;
        }

        $pagina = user_has_biometria::forInstitutions($instituciones)
            ->with('usuario')
            ->whereBetween('bio_created_at', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
            ->where('bio_state', true)
            ->orderByDesc('bio_created_at')
            ->paginate($this->scope->porPagina($request));

        return $this->paginado($pagina, fn ($bio) => [
            'bio_code'       => (int) $bio->bio_code,
            'ins_code'       => (int) $bio->bio_ins_code,
            'usuario'        => optional($bio->usuario)->usu_nmbcom,
            'tipo'           => $bio->bio_is_entrada ? 'entrada' : 'salida',
            'fecha'          => (string) $bio->bio_created_at,
            'lat'            => $bio->bio_lat,
            'lng'            => $bio->bio_lng,
            'sincronizado_en' => $bio->bio_sincronizado_en,
        ], ['rango' => compact('desde', 'hasta')]);
    }

    /**
     * GET api/portal/rondas — rondas con su cantidad de puntos registrados.
     */
    public function rondas(Request $request): JsonResponse
    {
        list($error, $instituciones, $desde, $hasta) = $this->contexto($request);
        if ($error) {
            return $error;
        }

        $pagina = ronda_cabecera::forInstitutions($instituciones)
            ->with('users')
            ->whereBetween('rc_fecha_inicio', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
            ->where('rc_estado', 1)
            ->orderByDesc('rc_fecha_inicio')
            ->paginate($this->scope->porPagina($request));

        return $this->paginado($pagina, fn ($rc) => [
            'rc_id'           => (int) $rc->rc_id,
            'ins_code'        => (int) $rc->rc_ins_code,
            'usuario'         => optional($rc->users)->usu_nmbcom,
            'estado'          => $rc->rc_estado_ronda,
            'fecha_inicio'    => $rc->rc_fecha_inicio,
            'fecha_fin'       => $rc->rc_fecha_fin,
            'puntos_recorridos' => \Modules\Administracion\Models\ronda_detalle::where('rd_rc_id', $rc->rc_id)
                ->where('rd_estado', 1)
                ->count(),
        ], ['rango' => compact('desde', 'hasta')]);
    }

    /**
     * GET api/portal/novedades — bitacora de novedades reportadas.
     */
    public function novedades(Request $request): JsonResponse
    {
        list($error, $instituciones, $desde, $hasta) = $this->contexto($request);
        if ($error) {
            return $error;
        }

        $pagina = Novedad::forInstitutions($instituciones)
            ->with('users')
            ->whereBetween('nv_fecha_hora', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
            ->where('nv_estado', 1)
            ->orderByDesc('nv_fecha_hora')
            ->paginate($this->scope->porPagina($request));

        return $this->paginado($pagina, fn ($nv) => [
            'nv_id'       => (int) $nv->nv_id,
            'ins_code'    => (int) $nv->nv_ins_code,
            'usuario'     => optional($nv->users)->usu_nmbcom,
            'observacion' => $nv->nv_observacion,
            'fecha'       => $nv->nv_fecha_hora,
            'foto'        => $nv->imagenUrl,
            'lat'         => $nv->nv_lat,
            'lng'         => $nv->nv_lng,
        ], ['rango' => compact('desde', 'hasta')]);
    }

    /**
     * GET api/portal/accesos — entradas y salidas de personas y vehiculos.
     */
    public function accesos(Request $request): JsonResponse
    {
        list($error, $instituciones, $desde, $hasta) = $this->contexto($request);
        if ($error) {
            return $error;
        }

        $consulta = Acceso::forInstitutions($instituciones)
            ->with(['persona', 'vehiculo', 'visitante'])
            ->whereBetween('ac_created_at', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
            ->where('ac_estado', true);

        if ($request->filled('tipo')) {
            $consulta->porTipo($request->input('tipo'));
        }
        if ($request->filled('estado')) {
            $consulta->estado($request->input('estado'));
        }

        $pagina = $consulta->orderByDesc('ac_created_at')
            ->paginate($this->scope->porPagina($request));

        return $this->paginado($pagina, fn ($ac) => [
            'ac_code'            => (int) $ac->ac_code,
            'ins_code'           => (int) $ac->ac_ins_code,
            'tipo'               => $ac->ac_tipo,
            'estado'             => $ac->ac_estado_acceso,
            'persona'            => optional($ac->persona)->ap_nombres . ' ' . optional($ac->persona)->ap_apellidos,
            'documento'          => optional($ac->persona)->ap_documento,
            'entrada'            => $ac->ac_created_at,
            'salida'             => $ac->ac_is_salida_fecha,
            'tiempo_permanencia' => $ac->tiempo_permanencia,
            'patente'            => optional($ac->vehiculo)->av_patente,
            'motivo'             => optional($ac->visitante)->avi_motivo,
        ], ['rango' => compact('desde', 'hasta')]);
    }

    /**
     * GET api/portal/alertas — alertas con su estado de atencion.
     */
    public function alertas(Request $request): JsonResponse
    {
        list($error, $instituciones, $desde, $hasta) = $this->contexto($request);
        if ($error) {
            return $error;
        }

        $consulta = Alertas::forInstitutions($instituciones)
            ->with('usuario')
            ->whereBetween('al_fecha', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
            ->where('al_estado', 1);

        if ($request->filled('prioridad')) {
            $consulta->porPrioridad($request->input('prioridad'));
        }

        $pagina = $consulta->orderByDesc('al_fecha')
            ->paginate($this->scope->porPagina($request));

        return $this->paginado($pagina, fn ($al) => [
            'al_code'         => (int) $al->al_code,
            'ins_code'        => (int) $al->al_ins_code,
            'usuario'         => optional($al->usuario)->usu_nmbcom,
            'observacion'     => $al->al_observacion,
            'prioridad'       => $al->al_prioridad,
            'estado'          => $al->al_estado_alerta,
            'fecha'           => optional($al->al_fecha)->format('Y-m-d H:i:s'),
            'tiempo_respuesta_min' => $al->tiempo_respuesta,
            'esta_retrasada'  => $al->esta_retrasada,
        ], ['rango' => compact('desde', 'hasta')]);
    }
}
