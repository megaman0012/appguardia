<?php

namespace Modules\MobileApp\Http\Controllers;

use App\generalTrait;
use App\Services\PresenceValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controller;

use Modules\Administracion\Models\user_has_biometria;

class BiometriaController extends Controller {

    use generalTrait;

    protected PresenceValidationService $presenceService;

    public function __construct(PresenceValidationService $presenceService)
    {
        $this->presenceService = $presenceService;
    }

    protected $biometrix = [
        'rules' => [
            'file' => 'required',
            'latitud' => 'required|numeric',
            'longitud' => 'required|numeric',
            'is_entrada' => 'required|boolean',
            'institucion' => 'required|integer',
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
        $biox->bio_created_user = $us->id;
        $biox->bio_updated_user = $us->id;
        $biox->save();

        return response()->json([
            'message' => 'Biometría cargada con éxito',
            'distancia_m' => $validarInst['distancia_m'],
        ]);

    }

}