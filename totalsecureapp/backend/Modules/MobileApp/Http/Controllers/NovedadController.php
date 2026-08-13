<?php

namespace Modules\MobileApp\Http\Controllers;

use App\generalTrait;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\Administracion\Models\Novedad;
use Modules\Administracion\Models\UserHasInstitucion;

class NovedadController extends Controller {

    use generalTrait;
    protected array $createRules = [
        'rules' => [
            'ins_code' => 'required',
            'nv_observacion' => 'required',
            'nv_lat' => 'required',
            'nv_lng' => 'required',
        ],
        'messages' => [
            'ins_code.required' => 'Campo intitucion es obligatorio',
            'nv_observacion.required' => 'Campo observacion es obligatorio',
            'nv_lat.required' => 'Campo latitud es obligatorio',
            'nv_lng.required' => 'Campo longitud es obligatorio',
        ]
    ];
    public function create(Request $request): JsonResponse {

        list($us, $tk) = $this->getSanctumSession($request);

        $validator = Validator::make($request->all(), $this->createRules['rules'], $this->createRules['messages']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        $ins = UserHasInstitucion::where( 'ui_usu_id', $us->id )->where( 'ui_ins_code', $request->ins_code )->where( 'ui_state', 1 )->first();
        if(!$ins){
            return $this->message_json('errors', 'Usuario no vinculado a institucion');
        }

        try{

            $nv = new Novedad();

            if ($request->hasFile('file')) {
                $file = $request->file('file');
                list($fileMoved, $fileName) = $this->storeFiles('novedad', $file, $us->id.'_'.$tk->tokenable_gs );
                if(!$fileMoved){
                    return $this->message_json('errors', 'Error al cargar imagen a servidor' );
                }
                $nv->nv_foto = $fileName;
            }

            $nv->nv_usu_id = $us->id;
            $nv->nv_ug_code = $tk->tokenable_gs;
            $nv->nv_ins_code = $request->ins_code;
            $nv->nv_observacion = $request->nv_observacion;
            $nv->nv_fecha_hora = date('Y-m-d H:i:s');
            $nv->nv_lat = $request->nv_lat;
            $nv->nv_lng = $request->nv_lng;
            $nv->nv_estado = 1;
            $nv->nv_created_user = $us->id;
            $nv->nv_updated_user = $us->id;
            $nv->save();

            return response()->json([ 'result' => 'success', 'message' => 'Novedad Cargada Correctamente']);
        }catch (Exception $e){
            return $this->message_json('errors', $e->getMessage());
        }

    }

    protected array $listByDateRules = [
        'rules' => [
            'date' => 'required',
            'ins_code' => 'required',
        ],
        'messages' => [
            'date.required' => 'Campo fecha es obligatorio',
            'ins_code.required' => 'Campo intitucion es obligatorio',
        ],
    ];

    public function listByDate(Request $request): JsonResponse {
        list($us, $tk) = $this->getSanctumSession($request);

        $validator = Validator::make($request->all(), $this->listByDateRules['rules'], $this->listByDateRules['messages']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        $ins = UserHasInstitucion::where( 'ui_usu_id', $us->id )
            ->where( 'ui_ins_code', $request->ins_code )
            ->where( 'ui_state', 1 )->first();

        if(!$ins){
            return $this->message_json('errors', 'Usuario no vinculado a institucion');
        }

        $bitacoras = Novedad::whereDate('nv_fecha_hora', $request->date)
            ->where( 'nv_usu_id', $us->id )
            ->where( 'nv_ins_code', $request->ins_code )
            ->where( 'nv_estado', 1 )
            ->get();

        $res = [];
        foreach ($bitacoras as $bit) {
            $res[] = array(
                'nv_id'          => $bit->nv_id,
                'nv_fecha_hora'  => $bit->nv_fecha_hora,
                'nv_observacion' => $bit->nv_observacion,
                'nv_foto'        => $bit->imagenUrl,
                'nv_lat'         => $bit->nv_lat,
                'nv_lng'         => $bit->nv_lng,
            );
        }

        return response()->json([
            'nvNovedad' => $res
        ]);

    }

}
