<?php

namespace Modules\MobileApp\Http\Controllers;

use App\generalTrait;
use App\Services\OfflineSyncService;
use App\Services\TurnoService;
use Carbon\Carbon;
use App\Services\PresenceValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controller;

use Modules\Administracion\Models\Turno;
use Modules\Administracion\Models\user_has_biometria;

class BiometriaController extends Controller {

    use generalTrait;

    protected PresenceValidationService $presenceService;
    protected OfflineSyncService $offlineSync;
    protected TurnoService $turnoService;

    public function __construct(
        PresenceValidationService $presenceService,
        OfflineSyncService $offlineSync,
        TurnoService $turnoService
    ) {
        $this->presenceService = $presenceService;
        $this->offlineSync = $offlineSync;
        $this->turnoService = $turnoService;
    }

    protected $biometrix = [
        'rules' => [
            'file' => 'required',
            'latitud' => 'required|numeric',
            'longitud' => 'required|numeric',
            'is_entrada' => 'required|boolean',
            'institucion' => 'required|integer',
            'client_uuid' => 'nullable|uuid',
            'ocurrido_en' => 'nullable|date',
        ],
        'messages' => [
            'file.required' => 'Archivo de imagen es obligatorio',
            'latitud.required' => 'Ubicacion latitud es obligatorio',
            'longitud.required' => 'Ubicacion longitud es obligatorio',
            'is_entrada.required' => 'Campo tipo marcacion es obligatorio',
            'institucion.required' => 'Campo institucion es obligatorio',
        ],
    ];

    public function biometria(Request $request): JsonResponse {

        list($us, $tk) = $this->getSanctumSession($request);

        $validator = Validator::make($request->all(), $this->biometrix['rules'], $this->biometrix['messages']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        $clientUuid = $request->input('client_uuid');

        // Antes de validar ubicacion y subir la foto: si el marcaje ya llego, se
        // responde con el existente. Un reintento no revalida GPS (el guardia ya
        // no esta ahi) ni vuelve a escribir la imagen.
        $yaSincronizada = $this->offlineSync->buscar(user_has_biometria::class, 'bio_client_uuid', $clientUuid);
        if ($yaSincronizada !== null) {
            return $this->respuestaBiometria($yaSincronizada, true, null);
        }

        $validarInst = $this->presenceService->validarUbicacion(
            $request->latitud,
            $request->longitud,
            $request->institucion
        );

        if (!$validarInst['valido']) {
            return $this->message_json('errors', $validarInst['motivo']);
        }

        $file = $request->file('file');
        list($fileMoved, $fileName) = $this->storeFiles('biometria', $file, $us->id.'_'.$tk->tokenable_gs );
        if(!$fileMoved){ return $this->message_json('errors', 'Error al cargar imagen a servidor'); }

        $biox = new user_has_biometria;
        $biox->bio_user_id = $us->id;
        $biox->bio_ug_code = $tk->tokenable_gs;
        $biox->bio_image_name = $fileName;
        $biox->bio_lat = $request->latitud;
        $biox->bio_lng = $request->longitud;
        $biox->bio_is_entrada = $request->is_entrada;
        $biox->bio_ins_code = $request->institucion;
        $biox->bio_state = true;
        $biox->bio_client_uuid = $clientUuid;
        $biox->bio_sincronizado_en = $this->offlineSync->sincronizadoEn();
        $biox->bio_created_user = $us->id;
        $biox->bio_updated_user = $us->id;
        $biox->bio_created_at = $this->offlineSync->ocurridoEn($request->input('ocurrido_en'));

        list($biox, $duplicada) = $this->offlineSync->registrar(
            user_has_biometria::class,
            'bio_client_uuid',
            $clientUuid,
            function () use ($biox) {
                $biox->save();
                return $biox;
            }
        );

        // El marcaje se vincula al turno programado aqui mismo. Antes exigia una
        // llamada aparte a turnos-vincular-marcaje que la app nunca hacia, asi
        // que tu_marcada_entrada quedaba en null: el cumplimiento marcaba 0% y el
        // cierre automatico declaraba ausentes a guardias que si trabajaron.
        $turno = $duplicada ? null : $this->vincularConTurno($biox, $request, $us->id);

        return $this->respuestaBiometria($biox, $duplicada, $validarInst['distancia_m'], $turno);

    }

    /**
     * Enlaza el marcaje con el turno del dia, si lo hay.
     *
     * Nunca hace fallar el marcaje: si no hay turno (la institucion no los usa)
     * o algo sale mal, la biometria ya quedo guardada y eso es lo que no se
     * puede perder.
     */
    private function vincularConTurno(user_has_biometria $biox, Request $request, int $usuarioId): ?Turno
    {
        try {
            // La hora del EVENTO, no la de llegada al servidor: con un marcaje
            // sincronizado horas despues, usar "ahora" inventaria una tardanza.
            $momento = Carbon::parse($this->offlineSync->ocurridoEn($request->input('ocurrido_en')));
            $esEntrada = (bool) $request->is_entrada;

            $turno = $this->turnoService->buscarTurnoParaMarcaje(
                $usuarioId,
                (int) $request->institucion,
                $momento,
                $esEntrada
            );

            if (!$turno) {
                return null;
            }

            $turno = $esEntrada
                ? $this->turnoService->vincularEntrada($turno, $biox->bio_code, $momento)
                : $this->turnoService->vincularSalida($turno, $biox->bio_code, $momento);

            // Enlace en el otro sentido, para poder ir del marcaje a su turno.
            $biox->bio_tu_code = $turno->tu_id;
            $biox->save();

            return $turno;
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    /**
     * Un duplicado responde 200 igual que un alta nueva, para que la APK lo marque
     * como sincronizado sin mostrar error al guardia.
     */
    private function respuestaBiometria(
        user_has_biometria $biox,
        bool $duplicada,
        $distancia,
        ?Turno $turno = null
    ): JsonResponse {
        return response()->json([
            'message'     => $duplicada ? 'Biometría ya sincronizada' : 'Biometría cargada con éxito',
            'bio_code'    => $biox->bio_code,
            'client_uuid' => $biox->bio_client_uuid,
            'duplicado'   => $duplicada,
            'distancia_m' => $distancia,
            // El guardia ve en el acto si su marcaje quedo enlazado al turno y
            // con cuanta tardanza, en vez de enterarse despues por un reporte.
            'turno'       => $turno ? [
                'tu_id'            => $turno->tu_id,
                'puesto'           => optional($turno->puesto)->pu_nombre,
                'hora_prevista'    => $turno->tu_hora_inicio_prevista,
                'estado'           => $turno->tu_estado,
                'minutos_tardanza' => $turno->tu_minutos_tardanza,
                'minutos_extras'   => $turno->tu_minutos_extras,
            ] : null,
        ]);
    }

}