<?php

namespace Modules\MobileApp\Http\Controllers;

use App\generalTrait;
use App\Services\TurnoService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\Administracion\Models\Turno;
use Modules\Administracion\Models\UserHasInstitucion;

class TurnoController extends Controller
{
    use generalTrait;

    protected TurnoService $turnoService;

    public function __construct(TurnoService $turnoService)
    {
        $this->turnoService = $turnoService;
    }

    /**
     * POST /api/turnos-del-dia
     * Lista turnos del dia para el usuario autenticado
     */
    public function turnosDelDia(Request $request): JsonResponse
    {
        list($us, $tk) = $this->getSanctumSession($request);

        $validator = Validator::make($request->all(), [
            'ins_code' => 'required|integer',
        ], [
            'ins_code.required' => 'Campo institucion es obligatorio',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        $ins = UserHasInstitucion::where('ui_usu_id', $us->id)
            ->where('ui_ins_code', $request->ins_code)
            ->where('ui_state', 1)
            ->first();
        if (!$ins) {
            return $this->message_json('errors', 'Usuario no vinculado a institucion');
        }

        $turnos = Turno::with('puesto')
            ->where('tu_usu_id', $us->id)
            ->where('tu_ins_code', $request->ins_code)
            ->where('tu_fecha', Carbon::today()->toDateString())
            ->where('tu_state', true)
            ->orderBy('tu_hora_inicio_prevista', 'asc')
            ->get();

        $res = [];
        foreach ($turnos as $t) {
            $res[] = [
                'tu_id' => $t->tu_id,
                'puesto' => optional($t->puesto)->pu_nombre,
                'tu_hora_inicio_prevista' => $t->tu_hora_inicio_prevista,
                'tu_hora_fin_prevista' => $t->tu_hora_fin_prevista,
                'tu_marcada_entrada' => $t->tu_marcada_entrada,
                'tu_marcada_salida' => $t->tu_marcada_salida,
                'tu_estado' => $t->tu_estado,
                'tu_minutos_tardanza' => $t->tu_minutos_tardanza,
                'tu_minutos_extras' => $t->tu_minutos_extras,
                'minutos_tardanza_display' => $t->minutos_tardanza_display,
                'minutos_extras_display' => $t->minutos_extras_display,
                'marcador' => $t->marcador ? $t->marcador->im_descripcion : null,
            ];
        }

        return response()->json(['turnos' => $res]);
    }

    /**
     * POST /api/turnos-vincular-marcaje
     * Vincula una marcacion biometrica reciente a un turno programado
     */
    public function vincularMarcaje(Request $request): JsonResponse
    {
        list($us, $tk) = $this->getSanctumSession($request);

        $validator = Validator::make($request->all(), [
            'ins_code' => 'required|integer',
            'tu_id' => 'required|integer',
            'tipo' => 'required|in:entrada,salida',
        ], [
            'ins_code.required' => 'Campo institucion es obligatorio',
            'tu_id.required' => 'Campo turno es obligatorio',
            'tipo.required' => 'Campo tipo es obligatorio',
            'tipo.in' => 'Tipo debe ser entrada o salida',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        $turno = Turno::where('tu_id', $request->tu_id)
            ->where('tu_usu_id', $us->id)
            ->where('tu_ins_code', $request->ins_code)
            ->where('tu_state', true)
            ->first();
        if (!$turno) {
            return $this->message_json('errors', 'Turno no encontrado o no pertenece al usuario');
        }

        // Buscar la marcacion biometrica mas reciente del usuario hoy
        $bio = \Modules\Administracion\Models\user_has_biometria::where('bio_user_id', $us->id)
            ->where('bio_ins_code', $request->ins_code)
            ->whereDate('bio_created_at', Carbon::today())
            ->orderBy('bio_code', 'desc')
            ->first();

        if (!$bio) {
            return $this->message_json('errors', 'No se encontro marcacion biometrica reciente');
        }

        if ($request->tipo === 'entrada') {
            if ($turno->tu_bio_entrada_code) {
                return $this->message_json('errors', 'Este turno ya tiene marcacion de entrada vinculada');
            }
            $turno = $this->turnoService->vincularEntrada(
                $turno,
                $bio->bio_code,
                Carbon::parse($bio->bio_created_at)
            );
        } else {
            if ($turno->tu_bio_salida_code) {
                return $this->message_json('errors', 'Este turno ya tiene marcacion de salida vinculada');
            }
            $turno = $this->turnoService->vincularSalida(
                $turno,
                $bio->bio_code,
                Carbon::parse($bio->bio_created_at)
            );
        }

        // Vincular tambien desde el lado de biometria
        $bio->bio_tu_code = $turno->tu_id;
        $bio->save();

        return response()->json([
            'result' => 'success',
            'message' => 'Marcaje vinculado al turno correctamente',
            'turno' => [
                'tu_id' => $turno->tu_id,
                'tu_estado' => $turno->tu_estado,
                'tu_minutos_tardanza' => $turno->tu_minutos_tardanza,
                'tu_minutos_extras' => $turno->tu_minutos_extras,
            ],
        ]);
    }

    /**
     * POST /api/turnos-cumplimiento
     * Vista de cumplimiento (solo para supervisor/admin)
     */
    public function cumplimiento(Request $request): JsonResponse
    {
        list($us, $tk) = $this->getSanctumSession($request);

        $validator = Validator::make($request->all(), [
            'ins_code' => 'required|integer',
            'fecha' => 'nullable|date',
        ], [
            'ins_code.required' => 'Campo institucion es obligatorio',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        $fecha = $request->fecha ?? Carbon::today()->toDateString();

        $turnos = Turno::with(['usuario', 'institucion', 'puesto'])
            ->where('tu_ins_code', $request->ins_code)
            ->where('tu_fecha', $fecha)
            ->where('tu_state', true)
            ->orderBy('tu_hora_inicio_prevista', 'asc')
            ->get();

        $res = [];
        foreach ($turnos as $t) {
            $usuario = $t->usuario;
            $res[] = [
                'tu_id' => $t->tu_id,
                'guardia' => $usuario ? trim($usuario->usu_nmbcom ?? $usuario->usu_nmb1 . ' ' . $usuario->usu_ape1) : 'N/A',
                'cedula' => $usuario->usu_cedula ?? 'N/A',
                'institucion' => $t->institucion->ins_descripcion ?? 'N/A',
                'puesto' => optional($t->puesto)->pu_nombre,
                'turno_esperado' => $t->tu_hora_inicio_prevista . ' - ' . $t->tu_hora_fin_prevista,
                'marco_entrada' => $t->tu_marcada_entrada,
                'marco_salida' => $t->tu_marcada_salida,
                'minutos_tardanza' => $t->tu_minutos_tardanza,
                'minutos_extras' => $t->tu_minutos_extras,
                'estado' => $t->tu_estado,
                'estado_badge' => $t->estado_badge,
            ];
        }

        return response()->json(['cumplimiento' => $res]);
    }
}
