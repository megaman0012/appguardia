<?php

namespace Modules\MobileApp\Http\Controllers;

use Illuminate\Support\Facades\DB;
use App\generalTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controller;
use Modules\Administracion\Models\Acceso;
use Modules\Administracion\Models\AccesoPersona;
use Modules\Administracion\Models\Bitacora;
use Modules\Administracion\Models\UserHasInstitucion;

class AccesoController extends Controller{

    use generalTrait;
    protected $accesox = [
        'rules' => [
            'latitud' => 'required',
            'longitud' => 'required',
            'institucion' => 'required',
            'tipoAc' => 'required|integer',
            'identificacion' => 'required',
            'nombres' => 'required',
            'apellidos' => 'required',
            'nombAcomp' => 'required_if:isAcomp,true',
            'patente' => 'required_if:tipoAc,4',
        ],
        'messages' => [
            'latitud.required' => 'Campo latitud es obigatorio',
            'longitud.required' => 'Campo longitud es obigatorio',
            'institucion.required' => 'Campo institucion es obigatorio',
            'tipoAc.required' => 'Campo tipo de acceso es obigatorio',
            'identificacion.required' => 'Campo identificacion es obigatorio',
            'nombres.required' => 'Campo nombres es obigatorio',
            'apellidos.required' => 'Campo apellidos es obigatorio',
            'nombAcomp.required_if' => 'Campo nombre acompañante es obigatorio',
            'patente.required_if' => 'Campo patente es obligatorio',
        ],
    ];

    public function acceso(Request $request): JsonResponse {

        list($us, $tk) = $this->getSanctumSession($request);

        $validator = Validator::make($request->all(), $this->accesox['rules'], $this->accesox['messages']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        $ins = UserHasInstitucion::where( 'ui_usu_id', $us->id )->where( 'ui_ins_code', $request->institucion )->where( 'ui_state', 1 )->first();
        if(!$ins){
            return $this->message_json('errors', 'Usuario no vinculado a institucion');
        }

        DB::beginTransaction();
        try{

            $ap = AccesoPersona::where('ap_documento', $request->identificacion )->first();

            if(!$ap){
                $ap = new AccesoPersona();
                $ap->ap_documento = $request->identificacion;
                $ap->ap_tip_doc   = 'CI';
                $ap->ap_nombres   = $request->nombres;
                $ap->ap_apellidos = $request->apellidos;
                $ap->ap_estado    = true;
                $ap->ap_created_user = $us->id;
                $ap->ap_updated_user = $us->id;
                $ap->save();
            }

            $acc = new Acceso();
            $acc->ac_usu_id = $us->id;
            $acc->ac_ug_code = $tk->tokenable_gs;
            $acc->ac_tipo = $request->tipoAc;
            $acc->ac_is_entrada = ( $request->isEntrada ) ? 1 : 0 ;
            $acc->ac_ap_code = $ap->ap_code;
            $acc->ac_lat = $request->latitud;
            $acc->ac_lng = $request->longitud;
            $acc->ac_ins_code = $request->institucion;
            $acc->ac_empresa = $request->empresa;
            $acc->ac_temperatura = $request->temperatura;
            $acc->ac_nombre_contrato = $request->nombreContacto;
            $acc->ac_bicicleta = $request->isBici ? 1 : 0 ;
            $acc->ac_is_acomp = $request->isAcomp ? 1 : 0 ;
            $acc->ac_nomb_acomp = $request->nombAcomp;
            $acc->ac_rut_acomp = $request->rutAcomp;

            $acc->ac_patente = $request->patente;
            $acc->ac_is_sello = $request->isSello ? 1 : 0 ;
            $acc->ac_is_neumatico = $request->isNeumaticos ? 1 : 0 ;
            $acc->ac_is_carro = $request->isCarro ? 1 : 0 ;
            $acc->ac_pta_llave = $request->isPtaConLlave ? 1 : 0 ;
            $acc->ac_kms = $request->kms;

            $acc->ac_observaciones = $request->observacion;

            if ($request->hasFile('file')) {
                $file = $request->file('file');
                list($fileMoved, $fileName) = $this->storeFiles('accesos', $file, $us->id.'_'.$tk->tokenable_gs );
                if(!$fileMoved){
                    DB::rollBack();
                    return $this->message_json('errors', 'Error al cargar imagen a servidor' );
                }
                $acc->ac_foto = $fileName;
            }
            $acc->ac_estado = true;
            $acc->ac_created_user = $us->id;
            $acc->ac_updated_user = $us->id;
            $acc->save();

            DB::commit();
            return response()->json(['message' => 'Acceso registrado con éxito']);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->message_json('errors', $e->getMessage() );
        }
    }

    protected array $getAccesosByInstRules = [
        'rules' => [
            'date' => 'required',
            'ins_code' => 'required',
        ],
        'messages' => [
            'date.required' => 'Campo fecha es obligatorio',
            'ins_code.required' => 'Campo intitucion es obligatorio',
        ],
    ];

    public function getAccesosByInst(Request $request): JsonResponse {
        list($us, $tk) = $this->getSanctumSession($request);

        $validator = Validator::make($request->all(), $this->getAccesosByInstRules['rules'], $this->getAccesosByInstRules['messages']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        $ins = UserHasInstitucion::where( 'ui_ins_code', $request->ins_code )->where( 'ui_state', 1 )->first();
        if(!$ins){
            return $this->message_json('errors', 'Usuario no vinculado a institucion');
        }

        $accesos = Acceso::with('accesoPersona')
            //->where( 'bt_usu_id', $us->id ) //solo por usuario
            ->whereDate('ac_created_at', $request->date)
            ->where( 'ac_ins_code', $request->ins_code )
            ->where( 'ac_estado', 1 )
            ->orderBy('ac_code', 'desc')
            ->get();

        $res = [];
        foreach ($accesos as $ac) {
            $ac->ac_foto = $ac->imagenUrl;
            $res[] = $ac->toArray();
        }

        return response()->json([
            'acAccByIns' => $res
        ]);

    }

    protected array $setAccesosOut = [
        'rules' => [
            'code' => 'required',
            'ins' => 'required',
        ],
        'messages' => [
            'code.required' => 'Campo fecha es obligatorio',
            'ins.required' => 'Campo intitucion es obligatorio',
        ],
    ];
    public function accesOut(Request $request): JsonResponse {
        list($us, $tk) = $this->getSanctumSession($request);

        $validator = Validator::make($request->all(), $this->setAccesosOut['rules'], $this->setAccesosOut['messages']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        $ins = UserHasInstitucion::where( 'ui_usu_id', $us->id )->where( 'ui_ins_code', $request->ins )->where( 'ui_state', 1 )->first();
        if(!$ins){
            return $this->message_json('errors', 'Usuario no vinculado a institucion');
        }
        $acc = Acceso::find($request->code);
        if(!$acc){
            return $this->message_json('errors', 'No se encontro informacion del codigo provisto');
        }
        if($acc->ac_is_entrada == 0){
            return $this->message_json('errors', 'Al acceso se le registro como salida previamente');
        }

        $acc->ac_is_entrada = 0;
        $acc->ac_lat_sal = $request->lat;
        $acc->ac_lng_sal = $request->lng;
        $acc->ac_is_salida_fecha = date('Y-m-d H:i:s');
        $acc->save();

        return response()->json(['message' => 'Acceso registrado con éxito']);

    }
}
