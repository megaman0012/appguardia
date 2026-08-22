<?php

namespace Modules\MobileApp\Http\Controllers;

use App\generalTrait;
use App\Services\OfflineSyncService;
use App\Services\PresenceValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controller;

use Modules\Administracion\Models\user_has_biometria;

class BiometriaController extends Controller {

    use generalTrait;

    protected PresenceValidationService $presenceService;
    protected OfflineSyncService $offlineSync;

    public function __construct(PresenceValidationService $presenceService, OfflineSyncService $offlineSync)
    {
        $this->presenceService = $presenceService;
        $this->offlineSync = $offlineSync;
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

        return $this->respuestaBiometria($biox, $duplicada, $validarInst['distancia_m']);

    }

    /**
     * Un duplicado responde 200 igual que un alta nueva, para que la APK lo marque
     * como sincronizado sin mostrar error al guardia.
     */
    private function respuestaBiometria(user_has_biometria $biox, bool $duplicada, $distancia): JsonResponse
    {
        return response()->json([
            'message'     => $duplicada ? 'Biometría ya sincronizada' : 'Biometría cargada con éxito',
            'bio_code'    => $biox->bio_code,
            'client_uuid' => $biox->bio_client_uuid,
            'duplicado'   => $duplicada,
            'distancia_m' => $distancia,
        ]);
    }

}