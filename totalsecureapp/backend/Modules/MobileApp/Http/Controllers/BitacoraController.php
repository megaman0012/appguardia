<?php

namespace Modules\MobileApp\Http\Controllers;

use App\generalTrait;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\Administracion\Models\Bitacora;
use Modules\Administracion\Models\UserHasInstitucion;

class BitacoraController extends Controller {

    use generalTrait;
    protected array $createRules = [
        'rules' => [
            'ins_code' => 'required',
            'bt_observacion' => 'required',
            'bt_lat' => 'required',
            'bt_lng' => 'required',
        ],
        'messages' => [
            'ins_code.required' => 'Campo intitucion es obligatorio',
            'bt_observacion.required' => 'Campo observacion es obligatorio',
            'bt_lat.required' => 'Campo latitud es obligatorio',
            'bt_lng.required' => 'Campo longitud es obligatorio',
        ]
    ];
    public function create(Request $request): JsonResponse {

        list($us, $tk) = $this->getSanctumSession($request);

        $validator = Validator::make($request->all(), $this->createRules['rules'], $this->createRules['messages']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        $ins = UserHasInstitucion::where( 'ui_usu_id', $us->id )
            ->where( 'ui_ins_code', $request->ins_code )
            ->where( 'ui_state', 1 )
            ->first();

        if(!$ins){
            return $this->message_json('errors', 'Usuario no vinculado a institucion');
        }

        try{

            $bt = new Bitacora();

            if ($request->hasFile('file')) {
                $file = $request->file('file');
                list($fileMoved, $fileName) = $this->storeFiles('bitacora', $file, $us->id.'_'.$tk->tokenable_gs );
                if(!$fileMoved){
                    return $this->message_json('errors', 'Error al cargar imagen a servidor' );
                }
                $bt->bt_foto = $fileName;
            }

            $bt->bt_usu_id = $us->id;
            $bt->bt_ug_code = $tk->tokenable_gs;
            $bt->bt_ins_code = $request->ins_code;
            $bt->bt_observacion = $request->bt_observacion;
            $bt->bt_fecha_hora = date('Y-m-d H:i:s');
            $bt->bt_lat = $request->bt_lat;
            $bt->bt_lng = $request->bt_lng;
            $bt->bt_estado = 1;
            $bt->bt_created_user = $us->id;
            $bt->bt_updated_user = $us->id;
            $bt->save();

            return response()->json([ 'result' => 'success', 'message' => 'Bitacora Cargada Correctamente']);
        }catch (\Exception $e){
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

        $ins = UserHasInstitucion::where( 'ui_usu_id', $us->id )->where( 'ui_ins_code', $request->ins_code )->where( 'ui_state', 1 )->first();
        if(!$ins){
            return $this->message_json('errors', 'Usuario no vinculado a institucion');
        }

        $bitacoras = Bitacora::whereDate('bt_fecha_hora', $request->date)
            ->where( 'bt_usu_id', $us->id )
            ->where( 'bt_ins_code', $request->ins_code )
            ->where( 'bt_estado', 1 )
            ->get();

        $res = [];
        foreach ($bitacoras as $bit) {
            $res[] = array(
                'bt_id'          => $bit->bt_id,
                'bt_fecha_hora'  => $bit->bt_fecha_hora,
                'bt_observacion' => $bit->bt_observacion,
                'bt_foto'        => $bit->imagenUrl,
                'bt_lat'         => $bit->bt_lat,
                'bt_lng'         => $bit->bt_lng,
            );
        }

        return response()->json([
            'btBitacora' => $res
        ]);

    }

}
