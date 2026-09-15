<?php

namespace Modules\MobileApp\Http\Controllers;

use App\generalTrait;
use App\Services\OfflineSyncService;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\Administracion\Models\Novedad;
use Modules\Administracion\Models\UserHasInstitucion;

class NovedadController extends Controller {

    use generalTrait;

    protected OfflineSyncService $offlineSync;

    public function __construct(OfflineSyncService $offlineSync)
    {
        $this->offlineSync = $offlineSync;
    }

    protected array $createRules = [
        'rules' => [
            'ins_code' => 'required',
            'nv_observacion' => 'required',
            'nv_lat' => 'required',
            'nv_lng' => 'required',
            'client_uuid' => 'nullable|uuid',
            'ocurrido_en' => 'nullable|date',
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

        $clientUuid = $request->input('client_uuid');

        // Se corta antes de mover la foto a disco: un reintento no debe dejar
        // archivos huerfanos ni volver a subir la imagen.
        $yaSincronizada = $this->offlineSync->buscar(Novedad::class, 'nv_client_uuid', $clientUuid);
        if ($yaSincronizada !== null) {
            return $this->respuestaNovedad($yaSincronizada, true);
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
            $nv->nv_fecha_hora = $this->offlineSync->ocurridoEn($request->input('ocurrido_en'));
            $nv->nv_lat = $request->nv_lat;
            $nv->nv_lng = $request->nv_lng;
            $nv->nv_estado = 1;
            $nv->nv_client_uuid = $clientUuid;
            $nv->nv_sincronizado_en = $this->offlineSync->sincronizadoEn();
            $nv->nv_created_user = $us->id;
            $nv->nv_updated_user = $us->id;

            list($nv, $duplicada) = $this->offlineSync->registrar(
                Novedad::class,
                'nv_client_uuid',
                $clientUuid,
                function () use ($nv) {
                    $nv->save();
                    return $nv;
                }
            );

            return $this->respuestaNovedad($nv, $duplicada);
        }catch (\Exception $e){
            return $this->message_json('errors', $e->getMessage());
        }

    }

    /**
     * Un duplicado responde igual que un alta nueva (200) para que la APK marque
     * el registro como sincronizado sin mostrar error al guardia.
     */
    private function respuestaNovedad(Novedad $nv, bool $duplicada): JsonResponse
    {
        return response()->json([
            'result'      => 'success',
            'message'     => $duplicada ? 'Novedad ya sincronizada' : 'Novedad Cargada Correctamente',
            'nv_id'       => $nv->nv_id,
            'client_uuid' => $nv->nv_client_uuid,
            'duplicado'   => $duplicada,
        ]);
    }

    protected array $listByDateRules = [
        'rules' => [
            'date' => 'required',
            'ins_code' => 'required',
            // Opcionales: el APK ya instalado no los manda y tiene que seguir
            // funcionando igual que antes, o sea un solo dia y solo lo propio.
            'dias' => 'nullable|integer|min:1|max:31',
            'alcance' => 'nullable|in:propias,local',
        ],
        'messages' => [
            'date.required' => 'Campo fecha es obligatorio',
            'ins_code.required' => 'Campo intitucion es obligatorio',
            'dias.max' => 'El rango no puede pasar de 31 dias',
            'alcance.in' => 'Alcance no valido',
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

        /*
         * `dias` mira hacia atras desde `date`. Sin el, un solo dia: es lo que
         * hacia antes y lo que sigue mandando el APK ya instalado.
         *
         * El guardia solo podia ver las novedades de HOY, asi que al recibir el
         * puesto no habia forma de leer lo que habia pasado en el turno anterior
         * -- que es justamente para lo que sirve una bitacora.
         */
        $dias = (int) ($request->input('dias') ?? 1);
        $hasta = \Carbon\Carbon::parse($request->date)->endOfDay();
        $desde = $hasta->copy()->subDays($dias - 1)->startOfDay();

        $query = Novedad::whereBetween('nv_fecha_hora', [$desde, $hasta])
            ->where( 'nv_ins_code', $request->ins_code )
            ->where( 'nv_estado', 1 );

        /*
         * `local` muestra las de todo el puesto; por defecto, solo las propias.
         *
         * No se cambio el valor por defecto a proposito: que un guardia empiece
         * a ver lo que escribieron sus companeros es una decision de operacion,
         * no un detalle tecnico. Asi se puede elegir desde la pantalla.
         */
        if ($request->input('alcance') !== 'local') {
            $query->where( 'nv_usu_id', $us->id );
        }

        // `with('users')`: sin esto, mostrar el autor de cada novedad dispara
        // una consulta por fila.
        $bitacoras = $query->with('users')->orderByDesc('nv_fecha_hora')->get();

        $res = [];
        foreach ($bitacoras as $bit) {
            $res[] = array(
                'nv_id'          => $bit->nv_id,
                'nv_fecha_hora'  => $bit->nv_fecha_hora,
                'nv_observacion' => $bit->nv_observacion,
                'nv_foto'        => $bit->imagenUrl,
                'nv_lat'         => $bit->nv_lat,
                'nv_lng'         => $bit->nv_lng,
                // Quien la escribio. Con `alcance=local` la lista trae las de
                // todo el puesto, y una novedad sin autor no se puede consultar
                // con nadie.
                'nv_usu_id'      => $bit->nv_usu_id,
                'nv_autor'       => optional($bit->users)->usu_nmbcom,
            );
        }

        return response()->json([
            'nvNovedad' => $res
        ]);

    }

}
