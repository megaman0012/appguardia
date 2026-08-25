<?php

namespace Modules\MobileApp\Http\Controllers;

use App\generalTrait;
use App\Services\VacanteService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\Administracion\Models\TurnoPostulacion;
use Modules\Administracion\Models\TurnoVacante;

/**
 * Turnos que quedaron sin cubrir, del lado del guardia.
 *
 * Solo ve los que realmente puede tomar: de un local donde está habilitado, sin
 * chocar con otro turno suyo y con descanso suficiente. Mostrarle turnos que no
 * puede aceptar es hacerle perder el viaje.
 */
class VacanteController extends Controller
{
    use generalTrait;

    public function __construct(private VacanteService $vacantes)
    {
    }

    /**
     * POST /api/vacantes-disponibles
     */
    public function disponibles(Request $request): JsonResponse
    {
        list($us, $tk) = $this->getSanctumSession($request);

        if (!$us->usu_acepta_extras) {
            return response()->json([
                'success'       => true,
                'acepta_extras' => false,
                'vacantes'      => [],
                'mensaje'       => 'Active "quiero cubrir turnos extra" en su perfil para ver los turnos disponibles.',
            ]);
        }

        $abiertas = TurnoVacante::abiertas()
            ->with(['puesto', 'institucion'])
            ->where('tv_fecha', '>=', Carbon::today()->subDay()->toDateString())
            ->orderBy('tv_fecha')
            ->orderBy('tv_hora_inicio')
            ->get();

        $postuladas = TurnoPostulacion::where('tp_usu_id', $us->id)
            ->whereIn('tp_tv_id', $abiertas->pluck('tv_id'))
            ->get()
            ->keyBy('tp_tv_id');

        $res = [];
        foreach ($abiertas as $vacante) {
            if (!$vacante->admitePostulaciones()) {
                continue;
            }
            if (!$this->vacantes->puedeCubrir($vacante, (int) $us->id)) {
                continue;
            }

            $postulacion = $postuladas->get($vacante->tv_id);

            $res[] = [
                'tv_id'          => $vacante->tv_id,
                'ins_code'       => $vacante->tv_ins_code,
                'local'          => optional($vacante->institucion)->ins_descripcion,
                'puesto'         => optional($vacante->puesto)->pu_nombre,
                'fecha'          => $vacante->tv_fecha->toDateString(),
                'hora_inicio'    => substr((string) $vacante->tv_hora_inicio, 0, 5),
                'hora_fin'       => substr((string) $vacante->tv_hora_fin, 0, 5),
                'motivo'         => TurnoVacante::MOTIVOS[$vacante->tv_motivo] ?? $vacante->tv_motivo,
                // Que sepa si es de su local o de otro de la ciudad: puede
                // cambiarle el viaje.
                'es_de_mi_local' => (int) $vacante->tv_ins_code === (int) $request->input('ins_code'),
                'postulado'      => $postulacion && $postulacion->tp_estado === TurnoPostulacion::POSTULADO,
                'estado_postulacion' => optional($postulacion)->tp_estado,
            ];
        }

        return response()->json([
            'success'       => true,
            'acepta_extras' => true,
            'vacantes'      => $res,
        ]);
    }

    /**
     * POST /api/vacantes-postular
     */
    public function postular(Request $request): JsonResponse
    {
        list($us, $tk) = $this->getSanctumSession($request);

        $validator = Validator::make($request->all(), [
            'tv_id' => 'required|integer',
        ], [
            'tv_id.required' => 'Campo vacante es obligatorio',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        $vacante = TurnoVacante::find($request->tv_id);
        if (!$vacante) {
            return $this->message_json('errors', 'La vacante no existe');
        }

        if (!$vacante->admitePostulaciones()) {
            // Sucede con una postulacion hecha sin señal que se sincroniza
            // tarde: no es un error del guardia, hay que decirselo claro.
            return response()->json([
                'success' => false,
                'estado'  => $vacante->tv_estado,
                'message' => $vacante->tv_estado === TurnoVacante::CUBIERTA
                    ? 'Ese turno ya fue cubierto por otro guardia.'
                    : 'Ese turno ya no está disponible.',
            ]);
        }

        $motivo = $this->vacantes->motivoParaNoCubrir($vacante, (int) $us->id);
        if ($motivo !== null) {
            return response()->json(['success' => false, 'message' => $motivo]);
        }

        $r = $this->vacantes->postular(
            $vacante,
            (int) $us->id,
            $request->input('client_uuid'),
            $request->input('ocurrido_en')
        );

        return response()->json([
            'success'   => true,
            'duplicado' => $r['duplicado'],
            'message'   => 'Su postulación fue registrada. El supervisor confirmará quién cubre el turno.',
            'tp_id'     => $r['postulacion']->tp_id,
        ]);
    }

    /**
     * POST /api/vacantes-retirar
     */
    public function retirar(Request $request): JsonResponse
    {
        list($us, $tk) = $this->getSanctumSession($request);

        $vacante = TurnoVacante::find($request->tv_id);
        if (!$vacante) {
            return $this->message_json('errors', 'La vacante no existe');
        }

        $retirada = $this->vacantes->retirar($vacante, (int) $us->id);

        return response()->json([
            'success' => true,
            'message' => $retirada ? 'Postulación retirada.' : 'No tenía una postulación vigente.',
        ]);
    }

    /**
     * POST /api/vacantes-mis-postulaciones
     *
     * Para que el guardia sepa en qué quedó lo que pidió, sin depender de que le
     * llegue el aviso.
     */
    public function misPostulaciones(Request $request): JsonResponse
    {
        list($us, $tk) = $this->getSanctumSession($request);

        $postulaciones = TurnoPostulacion::with(['vacante.puesto', 'vacante.institucion'])
            ->where('tp_usu_id', $us->id)
            ->orderByDesc('tp_id')
            ->limit(30)
            ->get();

        $res = [];
        foreach ($postulaciones as $p) {
            if (!$p->vacante) {
                continue;
            }

            $res[] = [
                'tp_id'       => $p->tp_id,
                'tv_id'       => $p->tp_tv_id,
                'estado'      => $p->tp_estado,
                'local'       => optional($p->vacante->institucion)->ins_descripcion,
                'puesto'      => optional($p->vacante->puesto)->pu_nombre,
                'fecha'       => $p->vacante->tv_fecha->toDateString(),
                'hora_inicio' => substr((string) $p->vacante->tv_hora_inicio, 0, 5),
                'hora_fin'    => substr((string) $p->vacante->tv_hora_fin, 0, 5),
            ];
        }

        return response()->json(['success' => true, 'postulaciones' => $res]);
    }

    /**
     * POST /api/perfil-extras
     *
     * El interruptor de "quiero cubrir turnos extra". Sin esto habría que
     * avisarle a todos, y en dos semanas nadie miraría los avisos.
     */
    public function aceptarExtras(Request $request): JsonResponse
    {
        list($us, $tk) = $this->getSanctumSession($request);

        $us->usu_acepta_extras = (bool) $request->input('acepta', false);
        $us->save();

        return response()->json([
            'success'       => true,
            'acepta_extras' => (bool) $us->usu_acepta_extras,
        ]);
    }
}
