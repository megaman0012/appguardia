<?php

namespace Modules\MobileApp\Http\Controllers;

use App\Services\OfflineSyncService;
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

    protected OfflineSyncService $offlineSync;

    public function __construct(OfflineSyncService $offlineSync)
    {
        $this->offlineSync = $offlineSync;
    }

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
            // Opcional a proposito: el APK ya compilado no lo envia y tiene que
            // seguir funcionando. La idempotencia entra cuando una app lo mande.
            'client_uuid' => 'nullable|uuid',
            'ocurrido_en' => 'nullable|date',
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

        $clientUuid = $request->input('client_uuid');

        // Si este movimiento ya llego, se devuelve el que esta y no se hace nada
        // mas. Va ANTES del chequeo de recepcion abierta a proposito: un
        // reintento del mismo movimiento no debe chocar contra la recepcion que
        // el propio intento anterior dejo abierta -- si no, el guardia recibe
        // «ya existe una recepcion» por su propio reintento.
        //
        // Con client_uuid nulo (el APK actual) esto no encuentra nada y el flujo
        // sigue igual que antes.
        $yaRegistrado = $this->offlineSync->buscar(
            MovimientoCabecera::class, 'mc_client_uuid', $clientUuid
        );

        if ($yaRegistrado !== null) {
            return response()->json([
                'message'   => 'Recepción registrada con éxito',
                'id'        => $yaRegistrado->mc_id,
                'duplicado' => true,
            ]);
        }

        // ¿Tiene una recepcion ABIERTA de esta lista?
        //
        // «Abierta» es no tener una devolucion posterior, y no simplemente
        // «existe una fila de tipo recepcion». La diferencia importa:
        //
        // `finishListMov` **muta** la fila de recepcion a devolucion, asi que en
        // lo que escribe la app un ciclo cerrado deja de tener fila de
        // recepcion y el chequeo simple alcanzaba. Pero el ETL de v1 cargo
        // **una fila por evento** -- 5.963 recepciones y 5.916 devoluciones --
        // porque v1 guardaba las dos fechas y colapsarlas habria perdido la de
        // recepcion.
        //
        // Con el chequeo simple, esas recepciones historicas se leian como
        // abiertas: **589 combinaciones bloqueadas, 212 guardias en 91 locales**
        // sin poder registrar inventario nunca mas. Con esta regla quedan 47,
        // que son exactamente los ciclos que en v1 nunca se cerraron.
        $existe = MovimientoCabecera::where('mc_ins_code', $request->ins_code)
            ->where('mc_lista_id', $request->list_code)
            ->where('mc_tipo', MovimientoCabecera::TIPO_RECEPCION)
            ->where('mc_usuario_id', $us->id)
            ->where('mc_estado', '!=', MovimientoCabecera::ESTADO_CANCELADO)
            ->whereNotExists(function ($q) use ($request, $us) {
                $q->select(DB::raw(1))
                    ->from('inv_movimiento_cabecera as d')
                    ->where('d.mc_tipo', MovimientoCabecera::TIPO_DEVOLUCION)
                    ->where('d.mc_estado', '!=', MovimientoCabecera::ESTADO_CANCELADO)
                    ->where('d.mc_ins_code', $request->ins_code)
                    ->where('d.mc_lista_id', $request->list_code)
                    ->where('d.mc_usuario_id', $us->id)
                    ->whereColumn('d.mc_fecha', '>=', 'inv_movimiento_cabecera.mc_fecha');
            })
            ->exists();

        if ($existe) {
            return $this->message_json('errors', 'Ya existe una recepción registrada para esta lista');
        }

        DB::beginTransaction();
        try {
            // registrar() atrapa la violacion de unicidad (23505) de dos
            // reintentos simultaneos y devuelve el que gano, en vez de fallar.
            // Es lo que cierra la carrera que el chequeo en PHP no puede cerrar.
            list($movimiento, $duplicado) = $this->offlineSync->registrar(
                MovimientoCabecera::class,
                'mc_client_uuid',
                $clientUuid,
                function () use ($request, $us, $clientUuid) {
                    return MovimientoCabecera::create([
                        'mc_ins_code'     => $request->ins_code,
                        'mc_lista_id'     => $request->list_code,
                        'mc_tipo'         => MovimientoCabecera::TIPO_RECEPCION,
                        'mc_usuario_id'   => $us->id,
                        // La fecha la manda el dispositivo: un movimiento hecho
                        // sin señal conserva su hora real. Sin `ocurrido_en`
                        // (APK actual) queda `now()`, como antes.
                        'mc_fecha'        => $this->offlineSync->ocurridoEn($request->input('ocurrido_en')),
                        'mc_lat'          => $request->latitud,
                        'mc_lng'          => $request->longitud,
                        'mc_estado'       => MovimientoCabecera::ESTADO_COMPLETADO,
                        'mc_created_user' => $us->id,
                        'mc_updated_user' => $us->id,
                        'mc_client_uuid'  => $clientUuid,
                        'mc_sincronizado_en' => $this->offlineSync->sincronizadoEn(),
                    ]);
                }
            );

            if ($duplicado) {
                DB::commit();

                return response()->json([
                    'message'   => 'Recepción registrada con éxito',
                    'id'        => $movimiento->mc_id,
                    'duplicado' => true,
                ]);
            }

            $productos = json_decode($request->productos);

            // Un solo INSERT en vez de uno por producto. Eran cuatro viajes a la
            // base por movimiento (4 items por lista en promedio), dentro de la
            // transaccion y sobre la red movil de la tablet.
            $ahora = now();
            $detalles = [];

            foreach ($productos as $item) {
                $detalles[] = [
                    'md_movimiento_id'    => $movimiento->mc_id,
                    'md_producto_id'      => $item->id_producto,
                    'md_cantidad_default' => $item->cantidaddf ?? 0,
                    'md_cantidad_real'    => $item->cantidad ?? 0,
                    'md_recibido'         => ($item->cantidad ?? 0) > 0,
                    'md_observacion'      => $item->nota ?? null,
                    'md_estado'           => MovimientoDetalle::ESTADO_OK,
                    'md_created_user'     => $us->id,
                    'md_updated_user'     => $us->id,
                    // Con insert() masivo Eloquent no llena los timestamps:
                    // hay que ponerlos, o quedan nulos y el detalle no se puede
                    // ordenar ni auditar.
                    'md_created_at'       => $ahora,
                    'md_updated_at'       => $ahora,
                ];
            }

            if ($detalles !== []) {
                MovimientoDetalle::insert($detalles);
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
            'longitud' => 'required',
            // Aca no hace falta client_uuid: `code_mov` ya identifica la fila que
            // se cierra, asi que el reintento es reconocible sin uuid. Solo se
            // suma la hora real del evento.
            'ocurrido_en' => 'nullable|date',
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

        // Este endpoint no crea filas: **muta** la recepcion y la convierte en
        // devolucion. Por eso un reintento nunca duplico nada... pero si volvia
        // a escribir `mc_fecha = now()`, y una devolucion hecha a las 07:00 sin
        // señal terminaba fechada a la hora en que la tablet recupero la red.
        // Si ya es devolucion, el ciclo esta cerrado y no se toca.
        if ($movimiento->mc_tipo === MovimientoCabecera::TIPO_DEVOLUCION) {
            return response()->json([
                'message'   => 'Devolución registrada con éxito',
                'id'        => $movimiento->mc_id,
                'duplicado' => true,
            ]);
        }

        DB::beginTransaction();
        try {
            $movimiento->update([
                'mc_tipo'         => MovimientoCabecera::TIPO_DEVOLUCION,
                'mc_estado'       => MovimientoCabecera::ESTADO_COMPLETADO,
                // Hora real de la devolucion. Sin `ocurrido_en` (APK actual)
                // queda `now()`, identico a antes.
                'mc_fecha'        => $this->offlineSync->ocurridoEn($request->input('ocurrido_en')),
                'mc_lat'          => $request->latitud,
                'mc_lng'          => $request->longitud,
                'mc_updated_user' => $us->id,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Devolución registrada con éxito',
                'id'      => $movimiento->mc_id,
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
            // Igual que en la recepcion: opcional, para no romper el APK actual.
            'client_uuid' => 'nullable|uuid',
            'ocurrido_en' => 'nullable|date',
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

        $clientUuid = $request->input('client_uuid');

        // Mismo reintento, misma baja. Sin esto el segundo envio chocaria
        // contra el indice unique de mc_client_uuid y el guardia recibiria el
        // error crudo de Postgres para una operacion que en realidad ya quedo
        // registrada.
        $yaRegistrada = $this->offlineSync->buscar(
            MovimientoCabecera::class, 'mc_client_uuid', $clientUuid
        );

        if ($yaRegistrada !== null) {
            return response()->json([
                'message'   => 'Baja registrada con éxito',
                'id'        => $yaRegistrada->mc_id,
                'duplicado' => true,
            ]);
        }

        DB::beginTransaction();
        try {
            list($movimiento, $duplicado) = $this->offlineSync->registrar(
                MovimientoCabecera::class,
                'mc_client_uuid',
                $clientUuid,
                function () use ($request, $us, $clientUuid) {
                    return MovimientoCabecera::create([
                        'mc_ins_code'        => $request->ins_code,
                        'mc_lista_id'        => $request->list_code,
                        'mc_tipo'            => MovimientoCabecera::TIPO_BAJA,
                        'mc_usuario_id'      => $us->id,
                        'mc_fecha'           => $this->offlineSync->ocurridoEn($request->input('ocurrido_en')),
                        'mc_lat'             => $request->latitud,
                        'mc_lng'             => $request->longitud,
                        'mc_observaciones'   => $request->motivo,
                        'mc_estado'          => MovimientoCabecera::ESTADO_COMPLETADO,
                        'mc_client_uuid'     => $clientUuid,
                        'mc_sincronizado_en' => $this->offlineSync->sincronizadoEn(),
                        'mc_created_user'    => $us->id,
                        'mc_updated_user'    => $us->id,
                    ]);
                }
            );

            if ($duplicado) {
                DB::commit();

                return response()->json([
                    'message'   => 'Baja registrada con éxito',
                    'id'        => $movimiento->mc_id,
                    'duplicado' => true,
                ]);
            }

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
