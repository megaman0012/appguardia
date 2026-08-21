<?php

namespace Modules\MobileApp\Http\Controllers;

use App\generalTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\Administracion\Models\Lista;
use Modules\Administracion\Models\ListaItem;
use Modules\Administracion\Models\MovimientoCabecera;
use Modules\Administracion\Models\MovimientoDetalle;
use Modules\Administracion\Models\ProductoCatalogo;
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

    public function allListByInst(Request $request): JsonResponse
    {
        list($us, $tk) = $this->getSanctumSession($request);
        $validator = Validator::make($request->all(), $this->getListByInstRules['rules'], $this->getListByInstRules['messages']);
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

        $listas = Lista::with(['items.producto' => function ($q) {
                $q->select([
                    'ipc_id', 'ipc_nombre', 'ipc_descripcion',
                    'ipc_especificacion', 'ipc_activo'
                ]);
            }])
            ->where('li_ins_code', $request->ins_code)
            ->where('li_activo', true)
            ->get()
            ->map(function ($lista) {
                $lista->setRelation('productos', $lista->items->map(function ($item) {
                    $producto = $item->producto;
                    $producto->cantidad_default = $item->lia_cantidad_default;
                    return $producto;
                }));
                return $lista;
            });

        return response()->json(['listas' => $listas]);
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

    public function saveListMov(Request $request): JsonResponse
    {
        list($us, $tk) = $this->getSanctumSession($request);
        $validator = Validator::make($request->all(), $this->getSaveMovInvy['rules'], $this->getSaveMovInvy['messages']);
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

        $existe = MovimientoCabecera::where('mc_ins_code', $request->ins_code)
            ->where('mc_lista_id', $request->list_code)
            ->where('mc_tipo', MovimientoCabecera::TIPO_RECEPCION)
            ->where('mc_usuario_id', $us->id)
            ->where('mc_estado', '!=', MovimientoCabecera::ESTADO_CANCELADO)
            ->exists();

        if ($existe) {
            return $this->message_json('errors', 'Ya existe una recepción registrada para esta lista');
        }

        DB::beginTransaction();
        try {
            $movimiento = MovimientoCabecera::create([
                'mc_ins_code'     => $request->ins_code,
                'mc_lista_id'     => $request->list_code,
                'mc_tipo'         => MovimientoCabecera::TIPO_RECEPCION,
                'mc_usuario_id'   => $us->id,
                'mc_fecha'        => now(),
                'mc_lat'          => $request->latitud,
                'mc_lng'          => $request->longitud,
                'mc_estado'       => MovimientoCabecera::ESTADO_COMPLETADO,
                'mc_created_user' => $us->id,
                'mc_updated_user' => $us->id,
            ]);

            $productos = json_decode($request->productos);

            foreach ($productos as $item) {
                MovimientoDetalle::create([
                    'md_movimiento_id'    => $movimiento->mc_id,
                    'md_producto_id'      => $item->id_producto,
                    'md_cantidad_default' => $item->cantidaddf ?? 0,
                    'md_cantidad_real'    => $item->cantidad ?? 0,
                    'md_recibido'         => ($item->cantidad ?? 0) > 0,
                    'md_observacion'      => $item->nota ?? null,
                    'md_estado'           => MovimientoDetalle::ESTADO_OK,
                    'md_created_user'     => $us->id,
                    'md_updated_user'     => $us->id,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Recepción registrada con éxito',
                'id'      => $movimiento->mc_id,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->message_json('errors', $e->getMessage());
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

    public function finishListMov(Request $request): JsonResponse
    {
        list($us, $tk) = $this->getSanctumSession($request);
        $validator = Validator::make($request->all(), $this->getFinishMovInvy['rules'], $this->getFinishMovInvy['messages']);
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

        $movimiento = MovimientoCabecera::where('mc_id', $request->code_mov)->first();

        if (!$movimiento) {
            return $this->message_json('errors', 'Movimiento no encontrado');
        }

        if ($movimiento->mc_estado === MovimientoCabecera::ESTADO_CANCELADO) {
            return $this->message_json('errors', 'El movimiento está cancelado');
        }

        DB::beginTransaction();
        try {
            $movimiento->update([
                'mc_tipo'         => MovimientoCabecera::TIPO_DEVOLUCION,
                'mc_estado'       => MovimientoCabecera::ESTADO_COMPLETADO,
                'mc_fecha'        => now(),
                'mc_lat'          => $request->latitud,
                'mc_lng'          => $request->longitud,
                'mc_updated_user' => $us->id,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Devolución registrada con éxito',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->message_json('errors', $e->getMessage());
        }
    }

    public function registrarBaja(Request $request): JsonResponse
    {
        list($us, $tk) = $this->getSanctumSession($request);

        $validator = Validator::make($request->all(), [
            'ins_code'  => 'required',
            'list_code' => 'required',
            'latitud'   => 'required',
            'longitud'  => 'required',
            'productos' => 'required',
            'motivo'    => 'required',
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

        DB::beginTransaction();
        try {
            $movimiento = MovimientoCabecera::create([
                'mc_ins_code'      => $request->ins_code,
                'mc_lista_id'      => $request->list_code,
                'mc_tipo'          => MovimientoCabecera::TIPO_BAJA,
                'mc_usuario_id'    => $us->id,
                'mc_fecha'         => now(),
                'mc_lat'           => $request->latitud,
                'mc_lng'           => $request->longitud,
                'mc_observaciones' => $request->motivo,
                'mc_estado'        => MovimientoCabecera::ESTADO_COMPLETADO,
                'mc_created_user'  => $us->id,
                'mc_updated_user'  => $us->id,
            ]);

            $productos = json_decode($request->productos);

            foreach ($productos as $item) {
                MovimientoDetalle::create([
                    'md_movimiento_id'    => $movimiento->mc_id,
                    'md_producto_id'      => $item->id_producto,
                    'md_cantidad_default' => $item->cantidad ?? 0,
                    'md_cantidad_real'    => $item->cantidad ?? 0,
                    'md_recibido'         => false,
                    'md_observacion'      => $item->observacion ?? null,
                    'md_estado'           => MovimientoDetalle::ESTADO_OK,
                    'md_created_user'     => $us->id,
                    'md_updated_user'     => $us->id,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Baja registrada con éxito',
                'id'      => $movimiento->mc_id,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->message_json('errors', $e->getMessage());
        }
    }
}
