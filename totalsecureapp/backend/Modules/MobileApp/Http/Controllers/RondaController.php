<?php

namespace Modules\MobileApp\Http\Controllers;

use App\generalTrait;
use App\Services\PresenceValidationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\Administracion\Models\InstitucionMarcadores;
use Modules\Administracion\Models\ronda_cabecera;
use Modules\Administracion\Models\ronda_detalle;

class RondaController extends Controller {

    use generalTrait;

    protected PresenceValidationService $presenceService;

    public function __construct(PresenceValidationService $presenceService)
    {
        $this->presenceService = $presenceService;
    }

    protected array $rondax = [
        'rules' => [ 'ins_code' => 'required' ],
        'messages' => [ 'ins_code.required' => 'Campo intitucion es obligatorio' ],
    ];

    protected array $rondaxGestion = [
        'rules' => [
            'ins_code' => 'required',
            'rc_code' => 'required',
            'rc_estado_ronda' => 'required',
            'rc_lat_inicio' => 'required_if:rc_estado_ronda,Iniciada',
            'rc_lng_inicio' => 'required_if:rc_estado_ronda,Iniciada',
            'rc_lat_fin' => 'required_if:rc_estado_ronda,Finalizada|required_if:rc_estado_ronda,Cancelada',
            'rc_lng_fin' => 'required_if:rc_estado_ronda,Finalizada|required_if:rc_estado_ronda,Cancelada',
        ],
        'messages' => [
            'ins_code.required' => 'Campo intitucion es obligatorio',
            'rc_code.required' => 'Campo ronda es obligatorio',
            'rc_estado_ronda.required' => 'Campo estado ronda es obligatorio',
            'rc_lat_inicio.required_if' => 'Campo latitud inicio es obligatorio',
            'rc_lng_inicio.required_if' => 'Campo longitud inicio es obligatorio',
            'rc_lat_fin.required_if' => 'Campo latitud fin es obligatorio',
            'rc_lng_fin.required_if' => 'Campo longitud fin es obligatorio',
        ],
    ];

    protected array $rondaxDetalle = [
        'rules' => [
            'ins_code' => 'required',
            'rc_id' => 'required',
        ],
        'messages' => [
            'ins_code.required' => 'Campo intitucion es obligatorio',
            'rc_id.required' => 'Campo ronda es obligatorio',
        ],
    ];

    protected array $rondaxDetallexGestion = [
        'rules' => [
            'ins_code' => 'required',
            'rc_id' => 'required',
            'rd_observacion' => 'required',
            'rd_lat' => 'required',
            'rd_lng' => 'required',
        ],
        'messages' => [
            'ins_code.required' => 'Campo intitucion es obligatorio',
            'rc_id.required' => 'Campo ronda es obligatorio',
            'rd_observacion.required' => 'Campo observacion es obligatorio',
            'rd_lat.required' => 'Campo latitud es obligatorio',
            'rd_lng.required' => 'Campo longitud es obligatorio',
        ],
    ];

    public function allRonda(Request $request): JsonResponse {

        list($us, $tk) = $this->getSanctumSession($request);

        $validator = Validator::make($request->all(), $this->rondax['rules'], $this->rondax['messages']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        $validarInst = $this->presenceService->validarInstitucion($request->ins_code);
        if (!$validarInst['valido']) {
            return $this->message_json('errors', $validarInst['motivo']);
        }

        $rondas = ronda_cabecera::where('rc_usu_code', $us->id)
            ->where('rc_ins_code', $request->ins_code)
            ->where('rc_estado', 1)
            ->orderBy('rc_id', 'desc')
            ->take(5)
            ->get();

        $res = [];
        $init = 0;
        foreach ($rondas as $rnd) {

            if($rnd->rc_estado_ronda == "Iniciada"){
                $init = $rnd->rc_id;
            }

            $res[] = array(
                'rc_id' => $rnd->rc_id,
                'rc_fecha_inicio' => $rnd->rc_fecha_inicio,
                'rc_fecha_fin' => $rnd->rc_fecha_fin,
                'rc_estado_ronda' => $rnd->rc_estado_ronda,
            );
        }

        return response()->json([
            'rondas' => $res,
            'inicio' => $init
        ]);

    }

    public function ronda_gestion(Request $request): JsonResponse{
        try{
            list($us, $tk) = $this->getSanctumSession($request);

            $validator = Validator::make($request->all(), $this->rondaxGestion['rules'], $this->rondaxGestion['messages']);
            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()]);
            }

            $validarInst = $this->presenceService->validarInstitucion($request->ins_code);
            if (!$validarInst['valido']) {
                return $this->message_json('errors', $validarInst['motivo']);
            }

            if($request->rc_estado_ronda == "Iniciada"){

                $rc = ronda_cabecera::where('rc_usu_code', $us->id)
                    ->where('rc_ins_code', $request->ins_code)
                    ->where('rc_estado', 1)
                    ->where('rc_estado_ronda', $request->rc_estado_ronda)
                    ->first();
                if($rc){
                    return $this->message_json('errors', 'Usuario posee ronda activa.');
                }

                $rc = new ronda_cabecera();
                $rc->rc_usu_code = $us->id;
                $rc->rc_ins_code = $request->ins_code;
                $rc->rc_ug_code = $tk->tokenable_gs;
                $rc->rc_fecha_inicio = date('Y-m-d H:i:s');
                $rc->rc_estado = 1;
                $rc->rc_estado_ronda = $request->rc_estado_ronda;
                $rc->rc_lat_inicio = $request->rc_lat_inicio;
                $rc->rc_lng_inicio = $request->rc_lng_inicio;
                $rc->rc_created_user = $us->id;

                $rc->save();

                return response()->json([ 'result' => 'success', 'message' => 'Ronda Iniciada Correctamente', 'estado'=>'Iniciada' ]);

            }else{
                if($request->rc_code == 0){
                    return $this->message_json('errors', 'Codigo de ronda es requerido');
                }

                $rc = ronda_cabecera::where('rc_id', $request->rc_code)->where('rc_estado', 1)->first();
                if(!$rc){
                    return $this->message_json('errors', 'Ronda desactivada o no existe');
                }

                if( $rc->rc_estado_ronda == $request->rc_estado_ronda ){
                    return $this->message_json('errors', 'Ronda actual ya fue '.$request->rc_estado_ronda);
                }

                $rc->rc_estado_ronda = $request->rc_estado_ronda;
                $rc->rc_fecha_fin = date('Y-m-d H:i:s');
                $rc->rc_lat_fin = $request->rc_lat_fin;
                $rc->rc_lng_fin = $request->rc_lng_fin;

                $rc->rc_updated_user = $us->id;
                $rc->save();

                return response()->json([ 'result' => 'success', 'message' => 'Ronda '.$request->rc_estado_ronda.' Correctamente', 'estado'=>$request->rc_estado_ronda ]);

            }
        }catch (Exception $e){
            return $this->message_json('errors', $e->getMessage());
        }
    }

    public function detalle_by_id_ronda(Request $request): JsonResponse {

        list($us, $tk) = $this->getSanctumSession($request);

        $validator = Validator::make($request->all(), $this->rondaxDetalle['rules'], $this->rondaxDetalle['messages']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        $validarInst = $this->presenceService->validarInstitucion($request->ins_code);
        if (!$validarInst['valido']) {
            return $this->message_json('errors', $validarInst['motivo']);
        }

        $rds = ronda_detalle::where('rd_rc_id', $request->rc_id)
            ->where('rd_ins_code', $request->ins_code)
            ->where('rd_usu_id', $us->id)
            ->where('rd_estado', 1)
            ->orderBy('rd_id', 'desc')
            ->get();

        $res = [];
        foreach ($rds as $rd) {
            $res[] = array(
                'rd_id'          => $rd->rd_id,
                'rd_im_code'     => $rd->rd_im_code,
                'rd_fecha_hora'  => $rd->rd_fecha_hora,
                'rd_observacion' => $rd->rd_observacion,
                'rd_foto'        => $rd->imagenUrl,
                'rd_lat'         => $rd->rd_lat,
                'rd_lng'         => $rd->rd_lng,
            );
        }

        return response()->json([
            'rdNovedades' => $res
        ]);

    }

    public function detalle_gestion(Request $request): JsonResponse {
        list($us, $tk) = $this->getSanctumSession($request);

        $validator = Validator::make($request->all(), $this->rondaxDetallexGestion['rules'], $this->rondaxDetallexGestion['messages']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        $validarInst = $this->presenceService->validarInstitucion($request->ins_code);
        if (!$validarInst['valido']) {
            return $this->message_json('errors', $validarInst['motivo']);
        }

        try{

            $rd = new ronda_detalle();

            if ($request->hasFile('file')) {
                $file = $request->file('file');
                list($fileMoved, $fileName) = $this->storeFiles('rondas', $file, $us->id.'_'.$tk->tokenable_gs );
                if(!$fileMoved){
                    return $this->message_json('errors', 'Error al cargar imagen a servidor' );
                }
                $rd->rd_foto = $fileName;
            }

            $rd->rd_usu_id = $us->id;
            $rd->rd_ug_code = $tk->tokenable_gs;
            $rd->rd_ins_code = $request->ins_code;
            $rd->rd_rc_id = $request->rc_id;
            $rd->rd_observacion = $request->rd_observacion;
            $rd->rd_fecha_hora = date('Y-m-d H:i:s');
            $rd->rd_lat = $request->rd_lat;
            $rd->rd_lng = $request->rd_lng;
            $rd->rd_estado = 1;
            $rd->rd_created_user = $us->id;
            $rd->rd_updated_user = $us->id;
            $rd->save();

            return response()->json([ 'result' => 'success', 'message' => 'Detalle Cargado Correctamente']);
        }catch (Exception $e){
            return $this->message_json('errors', $e->getMessage());
        }

    }

    protected array $rondaxDetallexQrCode = [
        'rules' => [
            'ins_code' => 'required',
            'rc_id' => 'required',
            'rc_qr' => 'required',
            'rd_lat' => 'required',
            'rd_lng' => 'required',
        ],
        'messages' => [
            'ins_code.required' => 'Campo intitucion es obligatorio',
            'rc_id.required' => 'Campo ronda es obligatorio',
            'rc_qr.required' => 'Campo qrcode es obligatorio',
            'rd_lat.required' => 'Campo latitud es obligatorio',
            'rd_lng.required' => 'Campo longitud es obligatorio',
        ],
    ];
    public function detalle_qrcode(Request $request): JsonResponse {
        list($us, $tk) = $this->getSanctumSession($request);

        $validator = Validator::make($request->all(), $this->rondaxDetallexQrCode['rules'], $this->rondaxDetallexQrCode['messages']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        $validacion = $this->presenceService->validarPresencia(
            $request->rc_qr,
            $request->rd_lat,
            $request->rd_lng,
            $request->ins_code
        );

        if (!$validacion['valido']) {
            return $this->message_json('errors', $validacion['motivo']);
        }

        $marcador = $validacion['marcador'];

        $ronDet = ronda_detalle::where('rd_im_code', $marcador->im_code)
            ->where('rd_usu_id', $us->id)
            ->where('rd_ins_code', $request->ins_code)
            ->orderBy('rd_id', 'desc')
            ->first();

        if($ronDet){
            $fechaRegistro = Carbon::parse($ronDet->rd_fecha_hora);
            $ahora = Carbon::now();
            $diferencia = $ahora->diffInMinutes($fechaRegistro);
            if ($diferencia < 5) {
                return $this->message_json('errors', 'Ya registro este marcador espere 5 minutos');
            }
        }

        try{
            $rd = new ronda_detalle();
            $rd->rd_usu_id = $us->id;
            $rd->rd_ug_code = $tk->tokenable_gs;
            $rd->rd_ins_code = $request->ins_code;
            $rd->rd_rc_id = $request->rc_id;
            $rd->rd_im_code = $marcador->im_code;
            $rd->rd_observacion = $marcador->im_descripcion;
            $rd->rd_fecha_hora = date('Y-m-d H:i:s');
            $rd->rd_lat = $request->rd_lat;
            $rd->rd_lng = $request->rd_lng;
            $rd->rd_estado = 1;
            $rd->rd_created_user = $us->id;
            $rd->rd_updated_user = $us->id;
            $rd->save();
            return response()->json([
                'result' => 'success',
                'message' => 'Novedad Cargada Correctamente',
                'distancia_m' => $validacion['distancia_m'],
            ]);
        }catch (Exception $e){
            return $this->message_json('errors', $e->getMessage());
        }
    }

}