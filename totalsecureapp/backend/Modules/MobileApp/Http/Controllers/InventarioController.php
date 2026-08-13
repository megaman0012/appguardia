<?php

namespace Modules\MobileApp\Http\Controllers;

use App\generalTrait;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\Administracion\Models\InvListaProducto;
use Modules\Administracion\Models\InvMovimiento;
use Modules\Administracion\Models\InvMovimientoDetalle;
use Modules\Administracion\Models\UserHasInstitucion;

class InventarioController extends Controller
{
    use generalTrait;
    protected array $getListByInstRules = [
        'rules' => [
            'ins_code' => 'required',
        ],
        'messages' => [
            'ins_code.required' => 'Campo intitucion es obligatorio',
        ],
    ];
    public function allListByInst(Request $request): JsonResponse {

        list($us, $tk) = $this->getSanctumSession($request);
        $validator = Validator::make($request->all(), $this->getListByInstRules['rules'], $this->getListByInstRules['messages']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        $ins = UserHasInstitucion::where( 'ui_usu_id', $us->id )->where( 'ui_ins_code', $request->ins_code )->where( 'ui_state', 1 )->first();
        if(!$ins){
            return $this->message_json('errors', 'Usuario no vinculado a institucion');
        }

        $listas = InvListaProducto::select( ['lp_id', 'lp_ins_code', 'lp_nombre', 'lp_descripcion', 'lp_estado'] )
            ->with(['productos' => function ($q) {
                $q->where('lpi_estado', 1)
                ->select(['lpi_id', 'lpi_lp_id', 'lpi_pr_id', 'lpi_cantidad', 'lpi_estado'])
                ->with(['producto' => function ($p) {
                    $p->select(['pr_id', 'pr_nombre', 'pr_especificacion', 'pr_descripcion', 'pr_estado']);
                }]);
            }])
            ->where('lp_ins_code', $request->ins_code)
            ->where('lp_estado', 1)
            ->get()
            ->map(function ($lista) {
                $lista->setRelation('productos', $lista->productos->map(function ($item) {
                    $producto = $item->producto;
                    $producto->cantidad_default = $item->lpi_cantidad;
                    return $producto;
                }));
                return $lista;
            });

        return response()->json([ 'listas' => $listas ]);
    }

    protected array $getSaveMovInvy = [
        'rules' => [
            'ins_code' => 'required',
            'list_code' => 'required',
            'latitud' => 'required',
            'longitud' => 'required',
            'productos' => 'required',
        ],
        'messages' => [
            'ins_code.required' => 'Campo intitucion es obligatorio',
            'list_code.required' => 'Campo lista producto es obligatorio',
            'latitud.required' => 'Campo latitud es obligatorio',
            'longitud.required' => 'Campo longitud es obligatorio',
            'productos.required' => 'Campo productos es obligatorio',
        ],
    ];
    public function saveListMov(Request $request): JsonResponse {
        list($us, $tk) = $this->getSanctumSession($request);
        $validator = Validator::make($request->all(), $this->getSaveMovInvy['rules'], $this->getSaveMovInvy['messages']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        $ins = UserHasInstitucion::where( 'ui_usu_id', $us->id )->where( 'ui_ins_code', $request->ins_code )->where( 'ui_state', 1 )->first();
        if(!$ins){
            return $this->message_json('errors', 'Usuario no vinculado a institucion');
        }

        $mov = InvMovimiento::where("mov_ins_code", $request->ins_code)
            ->where("mov_lp_id", $request->list_code)
            ->where("mov_tipo", "Recepcion")
            ->where("mov_recep_user", $us->id )
            ->where("mov_estado", 1)
            ->get();

        if ($mov->count() > 0) {
            return $this->message_json('errors', 'La recepcion ya fue regitrada');
        }

        DB::beginTransaction();
        try{

            $mv = new InvMovimiento();
            $mv->mov_ins_code = $request->ins_code;
            $mv->mov_lp_id = $request->list_code;
            $mv->mov_tipo = "Recepcion";
            $mv->mov_recep_user = $us->id;
            $mv->mov_recep_fecha = date('Y-m-d H:i:s');
            $mv->mov_recep_lat = $request->latitud;
            $mv->mov_recep_lng = $request->longitud;
            //$mv->mov_recep_obsv = $request->list_code;
            $mv->mov_created_at = date('Y-m-d H:i:s');
            $mv->mov_updated_at = date('Y-m-d H:i:s');
            $mv->mov_created_user = $us->id;
            $mv->mov_updated_user = $us->id;
            $mv->mov_estado = 1;
            $mv->save();

            $prods = json_decode($request->productos);

            foreach ($prods as $item) {

                $id_producto = $item->id_producto;
                $estado      = $item->estado;
                $cantidaddf  = $item->cantidaddf ?? 0;
                $cantidad    = $item->cantidad;
                $nota        = $item->nota;

                $md = new InvMovimientoDetalle();
                $md->md_mov_id = $mv->mov_id;
                $md->md_pr_id = $id_producto;
                $md->md_cant_asign = $cantidaddf;
                $md->md_cant_recep = $cantidad;
                $md->md_recep_obsv = $nota;
                $md->md_exist = $estado;
                $md->md_estado = 1;
                $md->md_created_at = date('Y-m-d H:i:s');
                $md->md_updated_at = date('Y-m-d H:i:s');
                $md->md_created_user = $us->id;
                $md->md_updated_user = $us->id;
                $md->save();
            }

            DB::commit();
            return response()->json(['message' => 'Lista registrada con éxito', 'id' => $mv->mov_id]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->message_json('errors', $e->getMessage() );
        }
    }

    protected array $getFinishMovInvy = [
        'rules' => [
            'ins_code' => 'required',
            'code_mov' => 'required',
            'latitud' => 'required',
            'longitud' => 'required'
        ],
        'messages' => [
            'ins_code.required' => 'Campo intitucion es obligatorio',
            'code_mov.required' => 'Campo lista producto es obligatorio',
            'latitud.required' => 'Campo latitud es obligatorio',
            'longitud.required' => 'Campo longitud es obligatorio'
        ],
    ];
    public function finishListMov(Request $request): JsonResponse {

        list($us, $tk) = $this->getSanctumSession($request);
        $validator = Validator::make($request->all(), $this->getFinishMovInvy['rules'], $this->getFinishMovInvy['messages']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        $ins = UserHasInstitucion::where( 'ui_usu_id', $us->id )->where( 'ui_ins_code', $request->ins_code )->where( 'ui_state', 1 )->first();
        if(!$ins){
            return $this->message_json('errors', 'Usuario no vinculado a institucion');
        }

        $mov = InvMovimiento::where("mov_id", $request->code_mov)->first();
        if ($mov) {
            $mov->mov_tipo = "Devolucion";
            $mov->mov_devol_user = $us->id;
            $mov->mov_devol_fecha = date('Y-m-d H:i:s');
            $mov->mov_devol_lat = $request->latitud;
            $mov->mov_devol_lng = $request->longitud;
            $mov->mov_updated_at = date('Y-m-d H:i:s');
            $mov->mov_updated_user = $us->id;
            $mov->save();
            return response()->json(['message' => 'Devolucion registrada con éxito']);
        }else{
            return $this->message_json('errors', 'No exite informacion de la lista provista');
        }
    }



}
